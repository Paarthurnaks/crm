<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;
use Illuminate\Support\Carbon;

class AccountFilter extends QueryFilter
{
    /**
     * @param int $dealId
     * @return void
     */
    public function deal_id(int $dealId): void
    {
        $this->builder->where("deal_id", $dealId);
    }

    /**
     * @param int $authorId
     * @return void
     */
    public function author_id(int $authorId): void
    {
        $this->builder->where("author_id", $authorId);
    }

    /**
     * @param string $date
     * @return void
     */
    public function invoice_date_from(string $date): void
    {
        $carbonDate = Carbon::parse($date);
        $this->builder->where("invoice_date", ">=", $carbonDate->startOfDay()->format('Y-m-d'));
    }

    /**
     * @param string $date
     * @return void
     */
    public function invoice_date_to(string $date): void
    {
        $carbonDate = Carbon::parse($date);
        $this->builder->where("invoice_date", "<=", $carbonDate->endOfDay()->format('Y-m-d'));
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
