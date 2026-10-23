<?php

namespace App\Http\Resources;

use App\Enums\DefaultRolesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="RoleResource",
     *     type="object",
     *     title="Роль",
     *     description="Ресурс роли, содержащий основную информацию о роли.",
     *
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         format="int64",
     *         description="Уникальный идентификатор роли"
     *     ),
     *     @OA\Property(
     *         property="title",
     *         type="string",
     *         description="Название роли"
     *     )
     * )
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_title' => $this->original_title,
            'is_client' => $this->original_title === DefaultRolesEnum::CLIENT->value,
            'is_partner' => $this->original_title === DefaultRolesEnum::PARTNER->value,
        ];
    }
}
