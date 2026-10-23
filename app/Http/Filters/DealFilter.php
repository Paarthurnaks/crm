<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Database\Query\JoinClause;

class DealFilter extends QueryFilter
{
    public function has_clients($value): void
    {
        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        $this->builder
            ->when($boolean, function ($query) {
                $query->whereHas('client');
            })
            ->when(!$boolean, function ($query) {
                $query->whereDoesntHave('client');
            });
    }

    /**
     * @param string $search
     * @return void
     */
    public function search(string $search): void
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
        $this->builder->when(!empty($title), function ($query) use ($title) {
            $query->where("title", 'like', "%{$title}%");
        });
    }

    /**
     * @param int $salesStageId
     * @return void
     *
     */
    public function sales_stage(int $salesStageId): void
    {
        $this->builder->where("sales_stage_id", $salesStageId);
    }

    /**
     * @param int $salesFunnelId
     * @return void
     *
     */
    public function sales_funnel(int $salesFunnelId): void
    {
        $this->builder->where("sales_funnel_id", $salesFunnelId);
    }


    /**
     * @param int $creatorId
     * @return void
     *
     */
    public function creator(int $creatorId): void
    {
        $this->builder->where("creator_id", $creatorId);
    }

    /**
     * @param int $responsibleId
     * @return void
     *
     */
    public function responsible(int $responsibleId): void
    {
        $this->builder->where("responsible_id", $responsibleId);
    }

    /**
     * @param string $note
     * @return void
     *
     */
    public function note(string $note): void
    {
        $this->builder->where("note", 'like', "%{$note}%");
    }

    /**
     * @param float $amountFrom
     * @return void
     */
    public function amount_from(float $amountFrom): void
    {
        $this->builder->where("amount", ">=", $amountFrom);
    }

    /**
     * @param float $amountTo
     * @return void
     */
    public function amount_to(float $amountTo): void
    {
        $this->builder->where("amount", "<=", $amountTo);
    }

    /**
     * @param string $client
     * @return void
     *
     */
    public function client(string $client): void
    {
        $this->builder->where("client", $client);
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
