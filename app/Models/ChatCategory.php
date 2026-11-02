<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatCategoryMapping> $chatCategoryMappings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ChatCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function chatCategoryMappings(): HasMany
    {
        return $this->hasMany(ChatCategoryMapping::class, 'chat_category_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'chat_category_mappings',
            'chat_category_id',
            'user_id'
        )->withPivot('chat_id');
    }
}
