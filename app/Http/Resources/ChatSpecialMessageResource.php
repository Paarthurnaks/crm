<?php

namespace App\Http\Resources;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatSpecialMessageResource extends JsonResource
{
    private int $userId;

    private ?Chat $chatContext;

    public function __construct($resource, $userId = null, ?Chat $chatContext = null)
    {
        parent::__construct($resource);
        $this->userId = $userId ?? auth()->id();
        $this->chatContext = $chatContext;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $chat = $this->chatContext ?? $this->chat;
        $partnerClientIds = $chat->getAttribute('partner_client_ids');
        $isPartnerClient = is_array($partnerClientIds)
            ? in_array((int) $this->user_id, $partnerClientIds, true)
            : null;

        $res = [
            'id' => $this->id,
            'text' => Str::limit($this->text, 15, '...'),
            'created_at' => $this->created_at,
            'attachments' => MediaResource::collection($this->getMedia()),
            'status' => $this->status,
            'user' => UserResource::make($this->user, $isPartnerClient),
        ];

        if ($chat->type === ChatTypeEnum::PARTNER->value) {
            $user = $chat->relationLoaded('members')
                ? $chat->members->firstWhere('user_id', $this->userId)?->user
                : \App\Models\User::find($this->userId);

            if ($user && ($user->role?->original_title === DefaultRolesEnum::PARTNER->value || $user->role?->title === DefaultRolesEnum::PARTNER->value)) {
                $res['user'] = [
                    'name' => 'CRM',
                    'surname' => 'Система',
                    'avatar' => Storage::url('avatars/esb_logo.png'),
                ];
            }
        }

        return $res;
    }
}
