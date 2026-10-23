<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class DocumentTemplateFilter extends QueryFilter
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
        $this->builder->where('title', 'like', "%{$title}%");
    }

    public function creator(int $creatorId): void
    {
        $this->builder->where('creator_id', $creatorId);
    }

    public function entity(string $entity): void
    {
        $this->builder->where('entity', $entity);
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
