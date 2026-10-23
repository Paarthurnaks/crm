<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class SalesStageFilter extends QueryFilter
{   
    /**
     * @param string $search
     * @return void
     */
    public function search(string $search)
    {
        $this->builder->where("title", "like", "%{$search}%");
    }

    /**
     * @param string $title
     * @return void
     * 
     */
    public function title(string $title): void
    {
        $this->builder->where("title", $title);
    }

    /**
     * @param string $color
     * @return void
     * 
     */
    public function color(string $color): void
    {
        $this->builder->where("color", $color);
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
     * @param string $type
     * @return void
     * 
     */
    public function type(string $type): void
    {
        $this->builder->where("type", $type);
    }

    /**
     * @param int $sales_funnel_id
     * @return void
     * 
     */
    public function sales_funnel_id(int $sales_funnel_id): void
    {
        $this->builder->where("sales_funnel_id", $sales_funnel_id);
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