<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class UserFieldFilter extends QueryFilter
{
    public function entity(string $entity): void
    {
        $this->builder->where('entity', $entity);
    }

    public function name(string $name): void
    {
        $this->builder->where('name', 'like', "%{$name}%");
    }

    public function type(string $type): void
    {
        $this->builder->where('type', $type);
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
