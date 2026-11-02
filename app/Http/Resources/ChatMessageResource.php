<?php

namespace App\Http\Resources;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultChatNameEnum;
use App\Enums\DefaultRolesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ChatMessageResource extends JsonResource
{
    private $userId;

    private $chatResource;

    public function __construct($resource, $userId = null, $chatResource = null)
    {
        parent::__construct($resource);

        $this->userId = $userId ?? auth()->id();
        $this->chatResource = $chatResource;
    }

    /**
     * @OA\Schema(
     *     schema="ChatMessageResource",
     *     title="Chat Message Resource",
     *     description="Represents a chat message in the API response.",
     *
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         description="The unique identifier of the message."
     *     ),
     *     @OA\Property(
     *         property="chat",
     *         ref="#/components/schemas/ChatResource",
     *         description="The chat where the message belongs."
     *     ),
     *     @OA\Property(
     *         property="user",
     *         ref="#/components/schemas/UserResource",
     *         description="The user who sent the message."
     *     ),
     *     @OA\Property(
     *         property="text",
     *         type="string",
     *         description="The text content of the message."
     *     ),
     *     @OA\Property(
     *          property="attachments",
     *          type="array",
     *
     *          @OA\Items(ref="#/components/schemas/MediaResource"),
     *          description="Array of attachments associated with the message."
     *      ),
     *
     *     @OA\Property(
     *         property="status",
     *         type="string",
     *         description="The status of the message (e.g., sent, delivered, read)."
     *     ),
     *     @OA\Property(
     *         property="reply_message_id",
     *         type="integer",
     *         nullable=true,
     *         description="The ID of the message to which this message is a reply."
     *     ),
     *     @OA\Property(
     *         property="is_forwarded",
     *         type="boolean",
     *         description="Indicates whether the message is a forwarded message."
     *     ),
     *     @OA\Property(
     *         property="is_read_by_current_user",
     *         type="boolean",
     *         description="Checks if the message has been read by the current user"
     *     ),
     *     @OA\Property(
     *         property="deletable_time",
     *         type="date-time",
     *         description="The date before which the message can be deleted."
     *     ),
     *     @OA\Property(
     *         property="forwarded_message_author",
     *         ref="#/components/schemas/UserResource",
     *         nullable=true,
     *         description="The author of the original forwarded message, if applicable."
     *     ),
     *     @OA\Property(
     *         property="created_at",
     *         type="string",
     *         format="date-time",
     *         description="The date and time when the message was created."
     *     ),
     *     @OA\Property(
     *         property="updated_at",
     *         type="string",
     *         format="date-time",
     *         description="The date and time when the message was last updated."
     *     )
     * )
     */
    public function toArray(Request $request): array
    {
        $res = [
            'id' => $this->id,
            'chat' => $this->when($this->chatResource, fn () => $this->chatResource, fn () => new ChatResource($this->chat, $this->userId)),
            'user' => $this->getUserResource(),
            'text' => $this->text,
            'attachments' => MediaResource::collection($this->getMedia()),
            'status' => $this->status,
            'is_read_by_current_user' => $this->resource->isReadByUser($this->userId),
            'reply_message' => $this->when($this->replyTo, fn () => new ChatMessageResource($this->replyTo, $this->userId)),
            'is_forwarded' => $this->is_forwarded,
            'deletable_time' => $this->created_at->addMinutes(config('app.message_lifetime', 1)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->forward && $this->forward->exists()) {
            if (is_null($this->forward->originalMessage)) {
                $res['forwarded_message_author'] = ['full_name' => DefaultChatNameEnum::DELETED_USER->value];
            } else {
                $res['forwarded_message_author'] = new UserResource($this->forward->originalMessage->user);
            }
        }

        return $res;
    }

    private function getUserResource(): UserResource|array
    {
        if ($this->chat->type === ChatTypeEnum::PARTNER->value) {
            $user = $this->chat->members->firstWhere('user_id', $this->userId)?->user;

            if ($user && $user->hasRole(DefaultRolesEnum::PARTNER->value) && $this->user->id !== $this->userId) {
                return [
                    'name' => 'CRM',
                    'surname' => 'Система',
                    'avatar' => Storage::url('avatars/esb_logo.png'),
                ];
            }
        }

        return new UserResource($this->user);
    }
}
