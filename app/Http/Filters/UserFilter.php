<?php

namespace App\Http\Filters;

use App\Enums\DefaultRolesEnum;
use App\Enums\UserOnlyParamTypeEnum;
use App\Http\Filters\Abstract\QueryFilter;

class UserFilter extends QueryFilter
{
    /**
     * @param int $id
     * @return void
     */
    public function linked_partner_id(int $id): void
    {
        $this->builder->where(function ($query) use ($id) {
            $query->whereHas('partner', function ($q) use ($id) {
                $q->where('partner_id', $id);
            });
        });
    }

    /**
     * @param string $search
     * @return void
     */
    public function search(string $search): void
    {
        $this->builder->whereRaw("LOWER(CONCAT(name, ' ', surname)) LIKE ?", ["%{$search}%"])
            ->orWhere("email", "like", "%{$search}%");;
    }

    /**
     * @param string $name
     * @return void
     *
     */
    public function name(string $name): void
    {
        $this->builder->where("name", $name);
    }

    public function only(string $type): void
    {
        if($type === UserOnlyParamTypeEnum::PARTNERS->value) {
            $this->builder->whereHas('role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::PARTNER->value);
            })->whereNotNull('role_id');
        } else if($type === UserOnlyParamTypeEnum::CLIENTS->value) {
            $this->builder->whereHas('role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::CLIENT->value);
            })->whereNotNull('role_id');
        } else if($type === UserOnlyParamTypeEnum::WITHOUT_CLIENTS->value) {
            $this->builder->whereHas('role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::PARTNER->value)
                    ->orWhere('original_title', '!=', DefaultRolesEnum::CLIENT->value);
            })->orWhereNull('role_id');
        } else if($type === UserOnlyParamTypeEnum::WITHOUT_PERSONALS->value) {
            $this->builder->whereHas('role', function ($query) {
                $query->whereNotIn('original_title', DefaultRolesEnum::havePersonalAccount());
            })->orWhereNull('role_id');
        } else {
            // чтобы ничего не выводилось, если не совпадает ни с одним
            $this->builder->whereRaw('1 = 0');
        }
    }

    /**
     * @param string $surname
     * @return void
     *
     */
    public function surname(string $surname): void
    {
        $this->builder->where("surname", $surname);
    }

    /**
     * @param string $email
     * @return void
     *
     */
    public function email(string $email): void
    {
        $this->builder->where("email", $email);
    }

    /**
     * @param int $role_id
     * @return void
     *
     */
    public function role(int $role_id): void
    {
        $this->builder->where("role_id", $role_id);
    }

    /**
     * @param int $division_id
     * @return void
     *
     */
    public function division(int $division_id): void
    {
        $this->builder->where("division_id", $division_id);
    }

    /**
     * @param bool $active
     * @return void
     *
     */
    public function active(bool $active): void
    {
        $this->builder->where("active", $active);
    }

    /**
     * @param string $fromDate
     * @return void
     *
     */
    public function last_login_from(string $fromDate): void
    {
        $this->builder->where("last_login", ">=", $fromDate);
    }

    /**
     * @param string $toDate
     * @return void
     *
     */
    public function last_login_to(string $toDate): void
    {
        $this->builder->where("last_login", "<=", $toDate);
    }

    /**
     * @param string $fromDate
     * @return void
     *
     */
    public function created_at_from(string $fromDate): void
    {
        $this->builder->where("created_at", ">=", $fromDate);
    }

    /**
     * @param string $toDate
     * @return void
     *
     */
    public function created_at_to(string $toDate): void
    {
        $this->builder->where("created_at", "<=", $toDate);
    }

    /**
     * @param string $fromDate
     * @return void
     *
     */
    public function updated_at_from(string $fromDate): void
    {
        $this->builder->where("updated_at", ">=", $fromDate);
    }

    /**
     * @param string $toDate
     * @return void
     *
     */
    public function updated_at_to(string $toDate): void
    {
        $this->builder->where("updated_at", "<=", $toDate);
    }
}
