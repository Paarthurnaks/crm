<?php

namespace App\Http\Resources;

use App\Enums\DefaultRolesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $res = [
            'id' => $this->id,
            'full_name' => "$this->name $this->surname",
            'name' => $this->name,
            'surname' => $this->surname,
            'email' => $this->email,
            'active' => $this->active,
            'color' => $this->color,
            'last_login' => $this->last_login,
            'role' => RoleDetailResource::make($this->role),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'avatar' => $this->getFirstMedia()?->getUrl() ?? Storage::disk('public')->url('avatars/avatar.png'),
        ];

        if ($request->user()?->hasRole(DefaultRolesEnum::CLIENT->value)) {
            $partnerDeals = $this->partnerDeals()->select('id', 'title')->get();
            if ($partnerDeals->isNotEmpty()) {
                $res['partner_deals'] = $partnerDeals;
            }
        }

        return $res;
    }
}
