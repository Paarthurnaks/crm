<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;
use Illuminate\Support\Carbon;

class AccountFilter extends QueryFilter
{
    public function deal_id(int $dealId): void
    {
        $this->builder->where('deal_id', $dealId);
    }

    public function author_id(int $authorId): void
    {
        $this->builder->where('author_id', $authorId);
    }

    public function invoice_date_from(string $date): void
    {
        $carbonDate = Carbon::parse($date);
        $this->builder->where('invoice_date', '>=', $carbonDate->startOfDay()->format('Y-m-d'));
    }

    public function invoice_date_to(string $date): void
    {
        $carbonDate = Carbon::parse($date);
        $this->builder->where('invoice_date', '<=', $carbonDate->endOfDay()->format('Y-m-d'));
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
