<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class SectionFilter extends QueryFilter
{
    public function entity(string $entity): void
    {
        $this->builder->where('entity', $entity);
    }

    public function user_id(int $user_id): void
    {
        $this->builder->where('user_id', $user_id);
    }

    public function is_template(bool $is_template): void
    {
        $this->builder->where('is_template', $is_template);
    }

    public function position(int $position): void
    {
        $this->builder->where('position', $position);
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
