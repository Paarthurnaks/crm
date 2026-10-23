<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class DealFilter extends QueryFilter
{
    public function has_clients($value): void
    {
        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        $this->builder
            ->when($boolean, function ($query) {
                $query->whereHas('client');
            })
            ->when(! $boolean, function ($query) {
                $query->whereDoesntHave('client');
            });
    }

    public function search(string $search): void
    {
        $this->builder->where('title', 'like', "%{$search}%");
    }

    public function title(string $title): void
    {
        $this->builder->when(! empty($title), function ($query) use ($title) {
            $query->where('title', 'like', "%{$title}%");
        });
    }

    public function sales_stage(int $salesStageId): void
    {
        $this->builder->where('sales_stage_id', $salesStageId);
    }

    public function sales_funnel(int $salesFunnelId): void
    {
        $this->builder->where('sales_funnel_id', $salesFunnelId);
    }

    public function creator(int $creatorId): void
    {
        $this->builder->where('creator_id', $creatorId);
    }

    public function responsible(int $responsibleId): void
    {
        $this->builder->where('responsible_id', $responsibleId);
    }

    public function note(string $note): void
    {
        $this->builder->where('note', 'like', "%{$note}%");
    }

    public function amount_from(float $amountFrom): void
    {
        $this->builder->where('amount', '>=', $amountFrom);
    }

    public function amount_to(float $amountTo): void
    {
        $this->builder->where('amount', '<=', $amountTo);
    }

    public function client(string $client): void
    {
        $this->builder->where('client', $client);
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
