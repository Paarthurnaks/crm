<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $chat_id
 * @property int $user_id
 * @property int $chat_category_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ChatCategoryMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'user_id',
        'chat_category_id',
    ];
}
