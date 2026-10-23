<?php

namespace App\Http\Filters;

use App\Enums\ChatCategoryEnum;
use App\Enums\ChatTypeEnum;
use App\Enums\DefaultChatNameEnum;
use App\Enums\DefaultRolesEnum;
use App\Http\Filters\Abstract\QueryFilter;
use App\Models\ChatCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatFilter extends QueryFilter
{
    /**
     * @param string $id
     * @return void
     */
    public function category_id(string $id): void
    {
        $this->builder->whereHas('categories', function ($query) use ($id) {
            $query->where('chat_categories.id', $id);
        });
    }

    /**
     * @param string $value
     * @return void
     */
    public function search_term(string $value): void
    {
        $chatCategory = ChatCategory::find($this->request->get('category_id'));

        $typeByCategory = match ($chatCategory?->name) {
            ChatCategoryEnum::SYSTEM_PARTNERS->value => ChatTypeEnum::PARTNER->value,
            ChatCategoryEnum::SYSTEM_PARTNER_CLIENTS->value => ChatTypeEnum::CLIENT->value,
            default => null
        };

        $searchTerm = Str::lower($value);

        $this->builder->when($searchTerm, function ($query) use ($searchTerm, $typeByCategory) {
            $user = auth()->user();

            $this->applySystemChatFilters($query, $searchTerm, $user);
            $this->applyCategoryBasedFilters($query, $typeByCategory, $searchTerm);
            $this->applyGeneralSearchFilters($query, $searchTerm);
        });
    }

    /**
     * @param $query
     * @param $searchTerm
     * @param $user
     * @return void
     */
    private function applySystemChatFilters($query, $searchTerm, $user): void
    {
        $esbSystemName = Str::lower(DefaultChatNameEnum::ESB_SYSTEM->value);
        $defaultName = Str::lower(DefaultChatNameEnum::DEFAULT->value);

        if (Str::contains($esbSystemName, $searchTerm) && $user->hasRole(DefaultRolesEnum::PARTNER->value)) {
            $query->orWhere('type', ChatTypeEnum::PARTNER->value);
        } elseif (Str::contains($defaultName, $searchTerm) && !$user->hasRole(DefaultRolesEnum::havePersonalAccount())) {
            $query->orWhere('type', ChatTypeEnum::DEFAULT->value);
        }
    }

    /**
     * @param $query
     * @param $typeByCategory
     * @param $searchTerm
     * @return void
     */
    private function applyCategoryBasedFilters($query, $typeByCategory, $searchTerm): void
    {
        if ($typeByCategory === ChatTypeEnum::PARTNER->value || auth()->user()->hasRole(DefaultRolesEnum::CLIENT->value)) {
            $this->applyPartnerFilter($query, $searchTerm);
        } elseif ($typeByCategory === ChatTypeEnum::CLIENT->value || auth()->user()->hasRole(DefaultRolesEnum::PARTNER->value)) {
            $this->applyClientFilter($query, $searchTerm);
        }
    }

    /**
     * @param $query
     * @param $searchTerm
     * @return void
     */
    private function applyPartnerFilter($query, $searchTerm): void
    {
        $query->orWhere(function ($subQuery) use ($searchTerm) {
            $subQuery->where('type', ChatTypeEnum::PARTNER->value)
                ->whereHas('members.user', function ($userQuery) use ($searchTerm) {
                    $userQuery->whereHas('role', function ($roleQuery) {
                        $roleQuery->where('original_title', DefaultRolesEnum::PARTNER->value);
                    })
                        ->where(DB::raw("LOWER(CONCAT(users.name, ' ', users.surname))"), 'LIKE', "%{$searchTerm}%")
                        ->where('users.id', '!=', auth()->id());
                });
        });
    }

    /**
     * @param $query
     * @param $searchTerm
     * @return void
     */
    private function applyClientFilter($query, $searchTerm): void
    {
        $query->orWhere(function ($subQuery) use ($searchTerm) {
            $subQuery->where('type', ChatTypeEnum::CLIENT->value)
                ->where(function ($memberQuery) use ($searchTerm) {
                    $memberQuery->whereHas('members.user', function ($userQuery) use ($searchTerm) {
                        $userQuery->whereHas('role', function ($roleQuery) {
                            $roleQuery->whereIn('original_title', [DefaultRolesEnum::CLIENT->value, DefaultRolesEnum::PARTNER->value]);
                        })
                            ->where(DB::raw("LOWER(CONCAT(users.name, ' ', users.surname))"), 'LIKE', "%{$searchTerm}%")
                            ->where('users.id', '!=', auth()->id());
                    });
                });
        });
    }

    /**
     * @param $query
     * @param $searchTerm
     * @return void
     */
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
                        $userQuery->where(DB::raw("LOWER(CONCAT(users.name, ' ', users.surname))"), 'LIKE', "%{$searchTerm}%")
                            ->where('users.id', '!=', auth()->id());
                    });
            });
        });
    }
}
