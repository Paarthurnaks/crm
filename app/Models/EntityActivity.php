<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityActivity extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'activityable_type',
        'activityable_id',
        'entity_id',
        'entity_type',
    ];

    /**
     * @return MorphTo
     */
    public function activityable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
