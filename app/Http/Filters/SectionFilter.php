<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class SectionFilter extends QueryFilter
{
    /**
     * @param string $entity
     * @return void
     */
    public function entity(string $entity): void
    {
        $this->builder->where("entity", $entity);
    }

    /**
     * @param int $user_id
     * @return void
     *
     */
    public function user_id(int $user_id): void
    {
        $this->builder->where("user_id", $user_id);
    }

    /**
     * @param bool $is_template
     * @return void
     *
     */
    public function is_template(bool $is_template): void
    {
        $this->builder->where("is_template", $is_template);
    }

    /**
     * @param int $position
     * @return void
     *
     */
    public function position(int $position): void
    {
        $this->builder->where("position", $position);
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
