<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     title="ChatMember",
 *     description="Модель для участника чата",
 *
 *     @OA\Xml(name="ChatMember"),
 *
 *     @OA\Property(
 *         property="id",
 *         title="ID",
 *         description="Идентификатор",
 *         format="int64",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="user_id",
 *         title="User ID",
 *         description="Идентификатор пользователя",
 *         format="int64",
 *         type="integer",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="chat_id",
 *         title="Chat ID",
 *         description="Идентификатор чата",
 *         format="int64",
 *         type="integer",
 *         example=3
 *     )
 * )
 *
 * @property int $id
 * @property int $chat_id
 * @property int $user_id
 * @property bool $is_creator
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Chat|null $chat
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ChatMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'chat_id',
        'user_id',
        'is_creator',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    // Связь с пользователем
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
