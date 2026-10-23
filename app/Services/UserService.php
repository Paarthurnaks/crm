<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Http\Filters\UserFilter;
use App\Mail\ConfirmEmailMail;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserCreateNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class UserService
{
    /**
     * @param UserFilter $filter
     * @param array $queryParams
     * @return LengthAwarePaginator
     */
    public function getUsers(UserFilter $filter, array $queryParams): LengthAwarePaginator
    {
        $user = auth()->user();
        $limit = $queryParams["limit"] ?? config("constants.paginator.limit");

        if($user->hasRole(DefaultRolesEnum::PARTNER->value)) {
            return $user->clients()->filter($filter)->paginate($limit);
        } else if ($user->hasAccess(PermissionEntityEnum::USERS->value, 'view', PermissionTypeEnum::ALL->value)) {
            return User::when(!isset($queryParams['linked_partner_id']), function ($query) {
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

    /**
     * @param array $userData
     * @return User
     */
    public function createUser(array $userData): User
    {
        if(auth()->user()->role->original_title === DefaultRolesEnum::PARTNER->value) {
            $userData["role_id"] = Role::where("original_title", DefaultRolesEnum::CLIENT->value)->value("id");
        }

        $user = User::create([
            "name" => $userData["name"],
            "surname" => $userData["surname"],
            "post" => $userData["post"] ?? null,
            "email" => $userData["email"],
            "password" => Str::random(60),
            "role_id" => $userData["role_id"] ?? null
        ]);

        $key = "reset_password_code_" . $userData["email"];
        $code = Str::random(64);

        Redis::set($key, $code);

        $user->notify(new UserCreateNotification($code, $user));

        if (request()->has('avatar')) {
            $user->addMediaFromRequest('avatar')->toMediaCollection();
        }

        return $user;
    }

    /**
     * @param User $user
     * @param array $userData
     * @return User
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function updateUser(User $user, array $userData): User
    {
        $user->update($userData);

        if (request()->has('avatar')) {
            $user->addMediaFromRequest('avatar')->toMediaCollection();
        }

        return $user;
    }

    /**
     * @param User $user
     * @return void
     */
    public function deleteUser(User $user): void
    {
        $user->delete();
    }
}
