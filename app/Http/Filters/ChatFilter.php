<?php

namespace App\Http\Filters;

use App\Enums\ChatCategoryEnum;
use App\Enums\ChatTypeEnum;
use App\Enums\DefaultChatNameEnum;
use App\Enums\DefaultRolesEnum;
use App\Http\Filters\Abstract\QueryFilter;
use App\Models\ChatCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatFilter extends QueryFilter
{
    private ?User $viewer = null;

    public function forUser(User $viewer): self
    {
        $this->viewer = $viewer;

        return $this;
    }

    public function category_id(string $id): void
    {
        $this->builder->whereHas('categories', function ($query) use ($id) {
            $query->where('chat_categories.id', $id);
        });
    }

    public function search_term(string $value): void
    {
        $chatCategory = ChatCategory::find($this->request->get('category_id'));

        $typeByCategory = match ($chatCategory?->name) {
            ChatCategoryEnum::SYSTEM_PARTNERS->value => ChatTypeEnum::PARTNER->value,
            ChatCategoryEnum::SYSTEM_PARTNER_CLIENTS->value => ChatTypeEnum::CLIENT->value,
            default => null
        };

        $searchTerm = Str::lower($value);
        Log::debug('chat.search.applied', ['category_type' => $typeByCategory]);

        $this->builder->when($searchTerm, function ($query) use ($searchTerm, $typeByCategory) {
            $user = $this->viewer ?? $this->request->user();

            $this->applySystemChatFilters($query, $searchTerm, $user);
            $this->applyCategoryBasedFilters($query, $typeByCategory, $searchTerm);
            $this->applyGeneralSearchFilters($query, $searchTerm);
        });
    }

    private function applySystemChatFilters($query, $searchTerm, $user): void
    {
        $esbSystemName = Str::lower(DefaultChatNameEnum::ESB_SYSTEM->value);
        $defaultName = Str::lower(DefaultChatNameEnum::DEFAULT->value);

        if (Str::contains($esbSystemName, $searchTerm) && $user->hasRole(DefaultRolesEnum::PARTNER->value)) {
            $query->orWhere('type', ChatTypeEnum::PARTNER->value);
        } elseif (Str::contains($defaultName, $searchTerm) && ! $user->hasRole(DefaultRolesEnum::havePersonalAccount())) {
            $query->orWhere('type', ChatTypeEnum::DEFAULT->value);
        }
    }

    private function applyCategoryBasedFilters($query, $typeByCategory, $searchTerm): void
    {
        if ($typeByCategory === ChatTypeEnum::PARTNER->value || $this->viewer->hasRole(DefaultRolesEnum::CLIENT->value)) {
            $this->applyPartnerFilter($query, $searchTerm);
        } elseif ($typeByCategory === ChatTypeEnum::CLIENT->value || $this->viewer->hasRole(DefaultRolesEnum::PARTNER->value)) {
            $this->applyClientFilter($query, $searchTerm);
        }
    }

    private function applyPartnerFilter($query, $searchTerm): void
    {
        $query->orWhere(function ($subQuery) use ($searchTerm) {
            $subQuery->where('type', ChatTypeEnum::PARTNER->value)
                ->whereHas('members.user', function ($userQuery) use ($searchTerm) {
                    $this->whereMemberNameMatches($userQuery, $searchTerm, [DefaultRolesEnum::PARTNER->value]);
                });
        });
    }

    private function applyClientFilter($query, $searchTerm): void
    {
        $query->orWhere(function ($subQuery) use ($searchTerm) {
            $subQuery->where('type', ChatTypeEnum::CLIENT->value)
                ->where(function ($memberQuery) use ($searchTerm) {
                    $memberQuery->whereHas('members.user', function ($userQuery) use ($searchTerm) {
                        $this->whereMemberNameMatches($userQuery, $searchTerm, [DefaultRolesEnum::CLIENT->value, DefaultRolesEnum::PARTNER->value]);
                    });
                });
        });
    }

    private function applyGeneralSearchFilters($query, $searchTerm): void
    {
        $query->orWhere(function ($subQuery) use ($searchTerm) {
            $subQuery->where(function ($groupQuery) use ($searchTerm) {
                $groupQuery->where('type', ChatTypeEnum::GROUP->value)
                    ->where(DB::raw('LOWER(name)'), 'LIKE', "%{$searchTerm}%");
            });

            $subQuery->orWhere(function ($singleQuery) use ($searchTerm) {
                $singleQuery->where('type', ChatTypeEnum::SINGLE->value)
                    ->whereHas('members.user', function ($userQuery) use ($searchTerm) {
                        $this->whereMemberNameMatches($userQuery, $searchTerm);
                    });
            });
        });
    }

    private function whereMemberNameMatches($query, string $searchTerm, array $roles = []): void
    {
        if ($roles !== []) {
            $query->whereHas('role', function ($roleQuery) use ($roles) {
                $roleQuery->whereIn('original_title', $roles);
            });
        }

        $query->where(DB::raw("LOWER(CONCAT(users.name, ' ', users.surname))"), 'LIKE', "%{$searchTerm}%")
            ->where('users.id', '!=', $this->viewer->id);
    }
}
