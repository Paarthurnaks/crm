<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Enums\EntityTypeEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Events\Role\RoleChangeBroadcast;
use App\Http\Filters\UserFilter;
use App\Http\Resources\UserResource;
use App\Models\Field;
use App\Models\PartnerClient;
use App\Models\Role;
use App\Models\SalesFunnel;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use App\Notifications\UserCreateNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class UserService
{
    public function __construct(private readonly ChatService $chatService) {}

    public function getUsers(UserFilter $filter, array $queryParams, User $actor): LengthAwarePaginator
    {
        $user = $actor;
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        if ($user->hasRole(DefaultRolesEnum::PARTNER->value)) {
            return $user->clients()->filter($filter)->paginate($limit);
        } elseif ($user->hasAccess(PermissionEntityEnum::USERS->value, 'view', PermissionTypeEnum::ALL->value)) {
            return User::when(! isset($queryParams['linked_partner_id']), function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('original_title', '!=', DefaultRolesEnum::CLIENT->value)
                        ->orWhereNull('original_title');
                })->orWhereDoesntHave('role');
            })->when(array_key_exists('linked_partner_id', $queryParams) && is_null($queryParams['linked_partner_id']), function ($query) {
                $query->whereRaw('1 = 0');
            })
                ->filter($filter)->paginate($limit);
        }

        return new LengthAwarePaginator([], 0, $limit);
    }

    public function createUser(array $userData, User $actor, ?UploadedFile $avatar = null): User
    {
        if ($actor?->role?->original_title === DefaultRolesEnum::PARTNER->value) {
            $userData['role_id'] = Role::where('original_title', DefaultRolesEnum::CLIENT->value)->value('id');
        }

        $user = $this->createProvisionedUser([
            'name' => $userData['name'],
            'surname' => $userData['surname'],
            'post' => $userData['post'] ?? null,
            'email' => $userData['email'],
            'password' => Str::random(60),
            'role_id' => $userData['role_id'] ?? null,
        ], $actor);

        $key = 'reset_password_code_'.$userData['email'];
        $code = Str::random(64);

        DB::afterCommit(function () use ($key, $code, $user): void {
            try {
                Redis::set($key, $code);
                $user->notify(new UserCreateNotification($code, $user));
            } catch (\Throwable $exception) {
                Log::error('user.invitation.failed', ['user_id' => $user->id, 'exception' => $exception::class]);
            }
        });

        if ($avatar !== null) {
            $user->addMedia($avatar)->toMediaCollection();
        }

        return $user;
    }

    public function createProvisionedUser(array $attributes, ?User $actor = null): User
    {
        Log::info('user.provision.started', ['actor_id' => $actor?->id]);

        try {
            return DB::transaction(function () use ($attributes, $actor): User {
                $user = User::create($attributes);
                $this->initializeSections($user);

                if ($actor?->hasRole(DefaultRolesEnum::PARTNER->value)) {
                    PartnerClient::create(['partner_id' => $actor->id, 'client_id' => $user->id]);
                    $user->color = $actor->color;
                    $user->save();
                }

                $this->chatService->setupChats($user);
                Log::info('user.provision.completed', ['user_id' => $user->id, 'actor_id' => $actor?->id]);

                return $user;
            });
        } catch (\Throwable $exception) {
            Log::error('user.provision.failed', ['actor_id' => $actor?->id, 'exception' => $exception::class]);
            throw $exception;
        }
    }

    private function initializeSections(User $user): void
    {
        $requiredFields = Field::where('is_required', true)->get()->groupBy('entity');

        foreach (EntityTypeEnum::cases() as $entity) {
            $section = Section::create([
                'user_id' => $user->id,
                'entity' => $entity->value,
                'title' => 'Общее',
                'position' => 1,
                'is_template' => true,
                'main_section' => true,
            ]);

            foreach ($requiredFields->get($entity->value, collect())->values() as $index => $field) {
                SectionField::create([
                    'section_id' => $section->id,
                    'field_id' => $field->id,
                    'position' => $index + 1,
                ]);
            }
        }

        foreach (SalesFunnel::all() as $funnel) {
            $section = Section::create([
                'user_id' => $user->id,
                'entity' => SectionFieldEntityEnum::DEALS->value,
                'title' => 'Общее',
                'position' => 1,
                'is_template' => true,
                'main_section' => true,
                'sales_funnel_id' => $funnel->id,
            ]);

            foreach ($funnel->fields()->pluck('fields.id') as $index => $fieldId) {
                SectionField::create([
                    'section_id' => $section->id,
                    'position' => $index + 1,
                    'field_id' => $fieldId,
                ]);
            }

            foreach ($funnel->userFields()->pluck('user_fields.id') as $index => $userFieldId) {
                SectionField::create([
                    'section_id' => $section->id,
                    'position' => $index + 1,
                    'user_field_id' => $userFieldId,
                ]);
            }
        }

        Log::debug('user.sections.initialized', ['user_id' => $user->id]);
    }

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function updateUser(User $user, array $userData, ?UploadedFile $avatar = null): User
    {
        Log::info('user.update.started', ['user_id' => $user->id]);

        try {
            DB::transaction(function () use ($user, $userData): void {
                $oldRoleId = $user->role_id;
                $user->update($userData);

                if ((int) $oldRoleId !== (int) $user->role_id) {
                    $this->chatService->setupChats($user);
                    DB::afterCommit(function () use ($user): void {
                        try {
                            broadcast(new RoleChangeBroadcast(UserResource::make($user)));
                        } catch (\Throwable $exception) {
                            Log::error('user.role_broadcast.failed', ['user_id' => $user->id, 'exception' => $exception::class]);
                        }
                    });
                }
            });
        } catch (\Throwable $exception) {
            Log::error('user.update.failed', ['user_id' => $user->id, 'exception' => $exception::class]);
            throw $exception;
        }

        if ($avatar !== null) {
            $user->addMedia($avatar)->toMediaCollection();
        }

        Log::info('user.update.completed', ['user_id' => $user->id]);

        return $user;
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }
}
