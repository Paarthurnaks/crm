<?php

namespace App\Http\Resources;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ChatResource extends JsonResource
{
    private int $userId;
    public function __construct($resource, $userId = null)
    {
        parent::__construct($resource);
        $this->userId = $userId ?? auth()->id();
    }

    /**
     * @OA\Schema(
     *     schema="ChatResource",
     *     type="object",
     *     title="Chat Resource",
     *     description="Структура данных чата.",
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         format="int64",
     *         description="Уникальный идентификатор чата."
     *     ),
     *     @OA\Property(
     *         property="type",
     *         type="string",
     *         description="Тип чата."
     *     ),
     *     @OA\Property(
     *         property="name",
     *         type="string",
     *         description="Отображаемое имя чата."
     *     ),
     *     @OA\Property(
     *         property="avatar",
     *         type="string",
     *         description="Ссылка на аватар чата."
     *     ),
     *     @OA\Property(
     *         property="last_message",
     *         type="object",
     *         nullable=true,
     *         description="Последнее сообщение в чате, если есть.",
     *         @OA\Property(
     *             property="text",
     *             type="string",
     *             description="Текст последнего сообщения с ограничением длины."
     *         ),
     *         @OA\Property(
     *             property="created_at",
     *             type="string",
     *             format="date-time",
     *             description="Дата и время создания последнего сообщения."
     *         ),
     *         @OA\Property(
     *             property="attachments",
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/MediaResource"),
     *             description="Array of attachments associated with the message."
     *         ),
     *     ),
     *     @OA\Property(
     *         property="unread_messages_count",
     *         type="integer",
     *         description="Количество непрочитанных сообщений в чате."
     *     ),
     *     @OA\Property(
     *         property="created_at",
     *         type="string",
     *         format="date-time",
     *         description="Дата и время создания чата."
     *     ),
     *     @OA\Property(
     *         property="updated_at",
     *         type="string",
     *         format="date-time",
     *         description="Дата и время последнего обновления чата."
     *     ),
     *     @OA\Property(
     *         property="description",
     *         type="string",
     *         description="Описание чата, актуально только для чатов типа GROUP."
     *     ),
     *     @OA\Property(
     *          property="can_delete",
     *          type="boolean",
     *          description="Можно ли удалить сообщение"
     *      )
     * )
     */
    public function toArray(Request $request): array
    {
        $chat = $this->resource;

        $lastMessage = $chat->lastMessage;
        $firstUnreadMessage = $this->firstUnreadMessage($chat);

        return [
            'id' => $chat->id,
            'type' => $chat->type,
            'name' => $chat->getDisplayName($this->userId),
            'avatar' => $chat->getDisplayAvatar($this->userId),
            'category' => $chat->category->isNotEmpty() ? ChatCategoryResource::make($chat->category->first()) : null,
            'first_unread_message' => $firstUnreadMessage ? ChatSpecialMessageResource::make($firstUnreadMessage, $this->userId) : null,
            'last_message' => $lastMessage ? ChatSpecialMessageResource::make($lastMessage, $this->userId) : null,
            'unread_messages_count' => $chat->unreadMessages->where('user_id', $this->userId)->count(),
            'created_at' => $chat->created_at,
            'updated_at' => $chat->updated_at,
            'description' => $chat->type === ChatTypeEnum::GROUP->value ? $chat->description : null,
            'can_delete' => $this->canDelete($chat),
        ];
    }

    /**
     * @param Chat $chat
     * @return ChatMessage|null
     */
    private function firstUnreadMessage(Chat $chat): ChatMessage|null
    {
        $unreadMessage = $chat->unreadMessages
            ->where('user_id', $this->userId)
            ->sortBy('created_at')
            ->first();

        return $unreadMessage?->message;
    }

    /**
     * @param Chat $chat
     * @return bool
     */
    private function canDelete(Chat $chat): bool
    {
        switch ($chat->type) {
            case ChatTypeEnum::GROUP->value:
                return Auth::check() && Auth::user()->isCreatorOfChat($chat);

            case ChatTypeEnum::SINGLE->value:
                return $chat->members->count() === 1;

            case ChatTypeEnum::PARTNER->value:
                $partnerExists = $chat->members->contains(function ($member) {
                    return $member->user->role->original_title === DefaultRolesEnum::PARTNER->value;
                });
                return !$partnerExists;

            case ChatTypeEnum::CLIENT->value:
                $clientExists = $chat->members->contains(function ($member) {
                    return $member->user->role->original_title === DefaultRolesEnum::CLIENT->value;
                });

                $partnerExists = $chat->members->contains(function ($member) {
                    return $member->user->role->original_title === DefaultRolesEnum::PARTNER->value;
                });

                return !$clientExists || !$partnerExists;

            default:
                return false;
        }
    }
}
