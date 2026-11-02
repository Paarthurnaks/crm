<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $activityable_type
 * @property int $activityable_id
 * @property string $entity_type
 * @property int $entity_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $activityable
 * @property-read \Illuminate\Database\Eloquent\Model|null $entity
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class EntityActivity extends Model
{
    use Filterable, HasFactory;

    protected $fillable = [
        'activityable_type',
        'activityable_id',
        'entity_id',
        'entity_type',
    ];

    public function activityable(): MorphTo
    {
        return $this->morphTo();
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
