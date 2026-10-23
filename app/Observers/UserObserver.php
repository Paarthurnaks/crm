<?php

namespace App\Observers;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Enums\EntityTypeEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Events\Role\RoleChangeBroadcast;
use App\Http\Resources\UserResource;
use App\Models\Chat;
use App\Models\Field;
use App\Models\PartnerClient;
use App\Models\SalesFunnel;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    public function __construct(
        private readonly ChatService $chatService,
    )
    {
    }

    /**
     * @param User $user
     * @return void
     */
    public function created(User $user): void
    {
        $fields = Field::where("is_required", true)->get();

        $enumsArr = EntityTypeEnum::cases();
        $entities = array_column($enumsArr, "value");

        foreach ($entities as $entity)
        {
            $section = Section::create([
                "user_id" => $user->id,
                "entity" => $entity,
                "title" => "Общее",
                "position" => 1,
                "is_template" => true,
                "main_section" => true
            ]);

            $entityFields = array_values(array_filter($fields->toArray(), function($field) use ($entity) {
                return $field["entity"] == $entity;
            }));

            for ($i = 0; $i < count($entityFields); $i++)
            {
                SectionField::create([
                    "section_id" => $section->id,
                    "field_id" => $entityFields[$i]["id"],
                    "position" => $i+1
                ]);
            }
        }

        // привязка клиента к партнеру
        if(Auth::check() && Auth::user()->hasRole(DefaultRolesEnum::PARTNER->value)) {
            PartnerClient::create([
                'partner_id' => Auth::id(),
                'client_id' => $user->id,
            ]);

            // Для клиента наследуем цвет от партнера.
            $user->update([
                'color' => Auth::user()->color,
            ]);
        }

        // инициализация шаблонов воронок
        $funnels = SalesFunnel::all();

        foreach ($funnels as $funnel) {
            $section = Section::create([
                'user_id' => $user->id,
                'entity' => SectionFieldEntityEnum::DEALS->value,
                'title' => 'Общее',
                'position' => 1,
                'is_template' => true,
                'main_section' => true,
                'sales_funnel_id' => $funnel->id
            ]);

            $fieldIds = $funnel->fields()->pluck('fields.id');
            $userFieldIds = $funnel->userFields()->pluck('user_fields.id');

            foreach ($fieldIds as $index => $fieldId) {
                SectionField::create([
                    'section_id' => $section->id,
                    'position' => $index + 1,
                    'field_id' => $fieldId,
                ]);
            }
            foreach ($userFieldIds as $index => $userFieldId) {
                SectionField::create([
                    'section_id' => $section->id,
                    'position' => $index + 1,
                    'user_field_id' => $userFieldId,
                ]);
            }
        }

        // инициализация чатов
        $this->chatService->setupChats($user);
    }

    /**
     * @param User $user
     * @return void
     */
    public function updated(User $user): void
    {
        if($user->isDirty('role_id')) {
            $this->chatService->setupChats($user);

            broadcast(new RoleChangeBroadcast(UserResource::make($user)));
        }
    }

    /**
     * @param User $user
     * @return void
     */
    public function deleting(User $user): void
    {
        // отправка событий об удалении связанных с юзером чатов
        $chats = Chat::where(function ($query) use ($user) {
            $query->where('type', ChatTypeEnum::SINGLE->value)
                ->when($user->hasRole(DefaultRolesEnum::PARTNER->value) || $user->hasRole(DefaultRolesEnum::CLIENT->value), function ($query) {
                    $query->orWhere('type', ChatTypeEnum::CLIENT->value);
                })
                ->when($user->hasRole(DefaultRolesEnum::PARTNER->value), function ($query) {
                    $query->orWhere('type', ChatTypeEnum::PARTNER->value);
                });
            })
            ->whereHas("members", function ($query) use ($user) {
                $query->where("user_id", $user->id);
            })
            ->get();

        foreach ($chats as $chat) {
            $this->chatService->broadcastDeleteToGlobal($chat, true);
        }

        // удаление связанных с юзером клиентов
        $clientIds = $user->clients()->pluck('users.id')->toArray();
        User::whereIn('id', $clientIds)->delete();
    }
}
