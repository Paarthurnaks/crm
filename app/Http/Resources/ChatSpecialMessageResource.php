<?php

namespace App\Http\Resources;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatSpecialMessageResource extends JsonResource
{
    private int $userId;
    public function __construct($resource, $userId = null)
    {
        parent::__construct($resource);
        $this->userId = $userId ?? auth()->id();
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $res = [
            'id' => $this->id,
            'text' => Str::limit($this->text, 15, '...'),
            'created_at' => $this->created_at,
            'attachments' => MediaResource::collection($this->getMedia()),
            'status' => $this->status,
            'user' => UserResource::make($this->user)
        ];

        if ($this->chat->type === ChatTypeEnum::PARTNER->value){
            $user = User::find($this->userId);

            if ($user && $user->hasRole(DefaultRolesEnum::PARTNER->value)) {
                $res['user'] = [
                    'name' => 'ЕСБ',
                    'surname' => 'Система',
                    'avatar' => Storage::url("avatars/esb_logo.png"),
                ];
            }
        }

        return $res;
    }
}
