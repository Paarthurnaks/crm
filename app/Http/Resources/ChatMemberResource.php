<?php

namespace App\Http\Resources;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMemberResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="ChatMemberResource",
     *     type="object",
     *
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         description="Идентификатор участника чата"
     *     ),
     *     @OA\Property(
     *         property="chat",
     *         type="object",
     *         ref="#/components/schemas/ChatResource",
     *         description="Информация о чате"
     *     ),
     *     @OA\Property(
     *         property="user",
     *         type="object",
     *         ref="#/components/schemas/UserResource",
     *         description="Информация о пользователе"
     *     ),
     *     @OA\Property(
     *         property="is_creator",
     *         type="boolean",
     *         description="Является ли пользователь создателем чата"
     *     ),
     *     @OA\Property(
     *         property="created_at",
     *         type="string",
     *         format="date-time",
     *         description="Дата и время создания участника чата"
     *     ),
     *     @OA\Property(
     *         property="updated_at",
     *         type="string",
     *         format="date-time",
     *         description="Дата и время последнего обновления участника чата"
     *     )
     * )
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chat' => ChatResource::make($this->chat),
            'user' => UserResource::make($this->user),
            'is_creator' => $this->is_creator,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
