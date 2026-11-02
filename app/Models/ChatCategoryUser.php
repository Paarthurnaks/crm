<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $chat_category_id
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ChatCategoryUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chat_category_id',
    ];
}
