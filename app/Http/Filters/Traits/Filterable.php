<?php

namespace App\Http\Filters\Traits;

use App\Http\Filters\Abstract\QueryFilter;
use Illuminate\Contracts\Database\Eloquent\Builder;

trait Filterable
{
    public function scopeFilter(Builder $builder, QueryFilter $filter)
    {
        $filter->apply($builder);
    }
}
