<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     title="UnreadChatMessage",
 *     description="Модель сообщения пользователя в чате",
 *
 *     @OA\Xml(name="UnreadChatMessage"),
 *
 *     @OA\Property(
 *         property="id",
 *         title="ID",
 *         description="Идентификатор сообщения",
 *         format="int64",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="chat_message_id",
 *         title="Chat Message ID",
 *         description="ID сообщения из чата",
 *         format="int64",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="user_id",
 *         title="User ID",
 *         description="ID пользователя",
 *         format="int64",
 *         type="integer",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="chat_id",
 *         title="Chat ID",
 *         description="ID чата",
 *         format="int64",
 *         type="integer",
 *         example=3
 *     )
 * )
 *
 * @property int $id
 * @property int $user_id
 * @property int $chat_id
 * @property int $chat_message_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ChatMessage|null $message
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class UnreadChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chat_id',
        'chat_message_id',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
