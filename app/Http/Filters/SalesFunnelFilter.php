<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class SalesFunnelFilter extends QueryFilter
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