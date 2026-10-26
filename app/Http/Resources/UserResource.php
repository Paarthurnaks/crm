<?php

namespace App\Http\Resources;

use App\Enums\DefaultRolesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    private ?bool $isPartnerClientOverride;

    public function __construct($resource, ?bool $isPartnerClientOverride = null)
    {
        parent::__construct($resource);
        $this->isPartnerClientOverride = $isPartnerClientOverride;
    }

    /**
     * @OA\Schema(
     *     schema="UserResource",
     *     type="object",
     *     title="Пользователь",
     *     description="Детальная информация о пользователе, включая его роль и аватар",
     *
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         format="int64",
     *         description="Уникальный идентификатор пользователя"
     *     ),
     *     @OA\Property(
     *         property="full_name",
     *         type="string",
     *         description="Имя и фамилия пользователя"
     *     ),
     *     @OA\Property(
     *         property="post",
     *         type="string",
     *         description="Должность пользователя"
     *     ),
     *     @OA\Property(
     *         property="email",
     *         type="string",
     *         format="email",
     *         description="Электронная почта пользователя"
     *     ),
     *     @OA\Property(
     *         property="active",
     *         type="boolean",
     *         description="Статус активности пользователя"
     *     ),
     *     @OA\Property(
     *         property="last_login",
     *         type="string",
     *         format="date-time",
     *         description="Дата и время последнего входа в систему"
     *     ),
     *     @OA\Property(
     *         property="role",
     *         type="object",
     *         ref="#/components/schemas/RoleResource",
     *         description="Роль пользователя"
     *     ),
     *     @OA\Property(
     *         property="created_at",
     *         type="string",
     *         format="date-time",
     *         description="Дата и время создания пользователя"
     *     ),
     *     @OA\Property(
     *         property="updated_at",
     *         type="string",
     *         format="date-time",
     *         description="Дата и время последнего обновления информации о пользователе"
     *     ),
     *     @OA\Property(
     *         property="avatar",
     *         type="string",
     *         description="Ссылка на аватар пользователя"
     *     )
     * )
     */
    public function toArray(Request $request): array
    {
        $res = [
            'id' => $this->id,
            'full_name' => "$this->name $this->surname",
            'name' => $this->name,
            'surname' => $this->surname,
            'post' => $this->post,
            'email' => $this->email,
            'active' => $this->active,
            'color' => $this->color,
            'last_login' => $this->last_login,
            'role' => RoleResource::make($this->role),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'avatar' => $this->getFirstMedia()?->getUrl() ?? Storage::disk('public')->url('avatars/avatar.png'),
        ];

        $viewerRole = $request->user()?->role;
        $viewerIsPartner = $viewerRole?->original_title === DefaultRolesEnum::PARTNER->value
            || $viewerRole?->title === DefaultRolesEnum::PARTNER->value;

        if ($viewerIsPartner && ($this->isPartnerClientOverride ?? $request->user()->clients()->where('users.id', $this->id)->exists())) {
            $partnerDeals = $this->resource->relationLoaded('partnerDeals')
                ? $this->partnerDeals->map(fn ($deal) => $deal->only(['id', 'title']))
                : $this->partnerDeals()->select('id', 'title')->get();
            if ($partnerDeals->isNotEmpty()) {
                $res['partner_deals'] = $partnerDeals;
            }
        }

        if ($this->role?->original_title === DefaultRolesEnum::SUPER_ADMIN->value) {
            $res['is_high_level_admin'] = $this->is_high_level_admin;
        }

        return $res;
    }
}
