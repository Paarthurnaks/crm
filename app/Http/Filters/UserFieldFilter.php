<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class UserFieldFilter extends QueryFilter
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
     * @param string $name
     * @return void
     */
    public function name(string $name): void
    {
        $this->builder->where("name", 'like', "%{$name}%");
    }

    /**
     * @param string $type
     * @return void
     */
    public function type(string $type): void
    {
        $this->builder->where("type", $type);
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
