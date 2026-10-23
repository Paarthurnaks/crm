<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ExcludeHiddenMessagesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereDoesntHave('hiddenByUsers', function ($query) {
            $query->where('user_id', auth()->id());
        });
    }
}
