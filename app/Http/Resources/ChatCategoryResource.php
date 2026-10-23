<?php

namespace App\Http\Resources;

use App\Enums\ChatCategoryEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatCategoryResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="ChatCategoryResource",
     *     type="object",
     *     title="Категория Чата",
     *     required={"id", "name"},
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         example=1
     *     ),
     *     @OA\Property(
     *         property="name",
     *         type="string",
     *         example="system"
     *     )
     * )
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
