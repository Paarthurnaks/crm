<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;

class DiskEntityFilter extends QueryFilter
{
    protected function applyDefaultFilters(): void
    {
        // если не передан deleted в запросе
        // то по умолчанию будут возвращаться записи с deleted = 0
        $deleted = $this->request->input('deleted', false);
        $this->deleted($deleted);
    }

    public function search(string $title): void
    {
        $this->builder->where('title', 'like', "%{$title}%");
    }

    public function type(string $type): void
    {
        $this->builder->where('type', $type);
    }

    public function path(string $path): void
    {
        $this->builder->where('path', $path);
    }

    public function who_created(string $who_created_id): void
    {
        $this->builder->where('who_created_id', $who_created_id);
    }

    public function who_changed(string $who_changed_id): void
    {
        $this->builder->where('who_changed_id', $who_changed_id);
    }

    public function size(string $size): void
    {
        $this->builder->where('size', $size);
    }

    public function deleted(string $deleted): void
    {
        $this->builder->where('deleted', filter_var($deleted, FILTER_VALIDATE_BOOLEAN));
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
