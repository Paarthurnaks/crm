<?php

namespace App\Http\Resources;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    private int $userId;

    private array $presentation;

    public function __construct($resource, $userId = null)
    {
        parent::__construct($resource);
        $this->userId = $userId ?? auth()->id();
        $prepared = $resource instanceof Chat ? $resource->getAttribute('chat_presentation') : null;
        $this->presentation = is_array($prepared) && ($prepared['user_id'] ?? null) === $this->userId
            ? $prepared
            : self::prepareForUser($resource, $this->userId);
    }

    /**
     * @OA\Schema(
     *     schema="ChatResource",
     *     type="object",
     *     title="Chat Resource",
     *     description="Структура данных чата.",
     *
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
     *
     *             @OA\Items(ref="#/components/schemas/MediaResource"),
     *             description="Array of attachments associated with the message."
     *         ),
     *     ),
     *
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
        $firstUnreadMessage = $this->presentation['first_unread_message'];

        return [
            'id' => $chat->id,
            'type' => $chat->type,
            'name' => $this->presentation['name'],
            'avatar' => $this->presentation['avatar'],
            'category' => $chat->category->isNotEmpty() ? ChatCategoryResource::make($chat->category->first()) : null,
            'first_unread_message' => $firstUnreadMessage ? ChatSpecialMessageResource::make($firstUnreadMessage, $this->userId, $chat) : null,
            'last_message' => $lastMessage ? ChatSpecialMessageResource::make($lastMessage, $this->userId, $chat) : null,
            'unread_messages_count' => $this->presentation['unread_messages_count'],
            'created_at' => $chat->created_at,
            'updated_at' => $chat->updated_at,
            'description' => $chat->type === ChatTypeEnum::GROUP->value ? $chat->description : null,
            'can_delete' => $this->presentation['can_delete'],
        ];
    }

    public static function prepareForUser(Chat $chat, int $userId): array
    {
        $unreadMessage = $chat->unreadMessages
            ->where('user_id', $userId)
            ->sortBy('created_at')
            ->first();

        return [
            'user_id' => $userId,
            'name' => $chat->getDisplayName($userId),
            'avatar' => $chat->getDisplayAvatar($userId),
            'first_unread_message' => $unreadMessage?->message,
            'unread_messages_count' => $chat->unreadMessages->where('user_id', $userId)->count(),
            'can_delete' => self::canDeleteForUser($chat, $userId),
        ];
    }

    private static function canDeleteForUser(Chat $chat, int $userId): bool
    {
        switch ($chat->type) {
            case ChatTypeEnum::GROUP->value:
                return $chat->members->contains(fn ($member) => (int) $member->user_id === $userId && (bool) $member->is_creator);

            case ChatTypeEnum::SINGLE->value:
                return $chat->members->count() === 1;

            case ChatTypeEnum::PARTNER->value:
                $partnerExists = $chat->members->contains(function ($member) {
                    return $member->user?->role?->original_title === DefaultRolesEnum::PARTNER->value;
                });

                return ! $partnerExists;

            case ChatTypeEnum::CLIENT->value:
                $clientExists = $chat->members->contains(function ($member) {
                    return $member->user?->role?->original_title === DefaultRolesEnum::CLIENT->value;
                });

                $partnerExists = $chat->members->contains(function ($member) {
                    return $member->user?->role?->original_title === DefaultRolesEnum::PARTNER->value;
                });

                return ! $clientExists || ! $partnerExists;

            default:
                return false;
        }
    }
}
