<?php

namespace App\Http\Filters;

use App\Enums\DefaultRolesEnum;
use App\Enums\UserOnlyParamTypeEnum;
use App\Http\Filters\Abstract\QueryFilter;

class UserFilter extends QueryFilter
{
    public function linked_partner_id(int $id): void
    {
        $this->builder->where(function ($query) use ($id) {
            $query->whereHas('partner', function ($q) use ($id) {
                $q->where('partner_id', $id);
            });
        });
    }

    public function search(string $search): void
    {
        $this->builder->whereRaw("LOWER(CONCAT(name, ' ', surname)) LIKE ?", ["%{$search}%"])
            ->orWhere('email', 'like', "%{$search}%");
    }

    public function name(string $name): void
    {
        $this->builder->where('name', $name);
    }

    public function only(string $type): void
    {
        if ($type === UserOnlyParamTypeEnum::PARTNERS->value) {
            $this->builder->whereHas('role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::PARTNER->value);
            })->whereNotNull('role_id');
        } elseif ($type === UserOnlyParamTypeEnum::CLIENTS->value) {
            $this->builder->whereHas('role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::CLIENT->value);
            })->whereNotNull('role_id');
        } elseif ($type === UserOnlyParamTypeEnum::WITHOUT_CLIENTS->value) {
            $this->builder->whereHas('role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::PARTNER->value)
                    ->orWhere('original_title', '!=', DefaultRolesEnum::CLIENT->value);
            })->orWhereNull('role_id');
        } elseif ($type === UserOnlyParamTypeEnum::WITHOUT_PERSONALS->value) {
            $this->builder->whereHas('role', function ($query) {
                $query->whereNotIn('original_title', DefaultRolesEnum::havePersonalAccount());
            })->orWhereNull('role_id');
        } else {
            // чтобы ничего не выводилось, если не совпадает ни с одним
            $this->builder->whereRaw('1 = 0');
        }
    }

    public function surname(string $surname): void
    {
        $this->builder->where('surname', $surname);
    }

    public function email(string $email): void
    {
        $this->builder->where('email', $email);
    }

    public function role(int $role_id): void
    {
        $this->builder->where('role_id', $role_id);
    }

    public function division(int $division_id): void
    {
        $this->builder->where('division_id', $division_id);
    }

    public function active(bool $active): void
    {
        $this->builder->where('active', $active);
    }

    public function last_login_from(string $fromDate): void
    {
        $this->builder->where('last_login', '>=', $fromDate);
    }

    public function last_login_to(string $toDate): void
    {
        $this->builder->where('last_login', '<=', $toDate);
    }

    public function created_at_from(string $fromDate): void
    {
        $this->builder->where('created_at', '>=', $fromDate);
    }

    public function created_at_to(string $toDate): void
    {
        $this->builder->where('created_at', '<=', $toDate);
    }

    public function updated_at_from(string $fromDate): void
    {
        $this->builder->where('updated_at', '>=', $fromDate);
    }

    public function updated_at_to(string $toDate): void
    {
        $this->builder->where('updated_at', '<=', $toDate);
    }
}
