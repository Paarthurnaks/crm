<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class SalesStageFilter extends QueryFilter
{
    /**
     * @return void
     */
    public function search(string $search)
    {
        $this->builder->where('title', 'like', "%{$search}%");
    }

    public function title(string $title): void
    {
        $this->builder->where('title', $title);
    }

    public function color(string $color): void
    {
        $this->builder->where('color', $color);
    }

    public function position(int $position): void
    {
        $this->builder->where('position', $position);
    }

    public function type(string $type): void
    {
        $this->builder->where('type', $type);
    }

    public function sales_funnel_id(int $sales_funnel_id): void
    {
        $this->builder->where('sales_funnel_id', $sales_funnel_id);
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
