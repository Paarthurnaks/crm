<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Division|null $parent
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Division extends Model
{
    use HasFactory;

    const COMMON = 1;

    protected $fillable = [
        'title',
        'parent_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'parent_id');
    }
}
