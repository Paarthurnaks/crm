<?php

namespace App\Models;

use App\Models\Scopes\ChatMessagesScope;
use App\Models\Scopes\ExcludeHiddenMessagesScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property $chat_id
 * @property $user_id
 * @property $status_id
 * @property $text_id
 * @property $reply_message_id
 * @property $is_forwarded
 *
 * @OA\Schema(
 *     title="ChatMessagel",
 *     description="Модель сообщения",
 *
 *     @OA\Xml(name="ChatMessage"),
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
 *         property="chat_id",
 *         title="Chat ID",
 *         description="Идентификатор чата",
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
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="status",
 *         title="Status",
 *         description="Статус сообщения",
 *         type="string",
 *         enum={"sent", "delivered", "read"},
 *         example="sent"
 *     ),
 *     @OA\Property(
 *         property="text",
 *         title="Text",
 *         description="Текст сообщения",
 *         type="string",
 *         example="Привет, мир!"
 *     ),
 *     @OA\Property(
 *         property="reply_message_id",
 *         title="Reply Message ID",
 *         description="ID сообщения, на которое ответили",
 *         format="int64",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="is_forwarded",
 *         title="Is Forwarded",
 *         description="Флаг, указывающий, является ли сообщение пересланным",
 *         type="boolean",
 *         example=true
 *     )
 * )
 */
class ChatMessage extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'chat_id',
        'user_id',
        'status',
        'text',
        'reply_message_id',
        'is_forwarded',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(new ExcludeHiddenMessagesScope);
        static::addGlobalScope(new ChatMessagesScope);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'reply_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'reply_message_id');
    }

    public function hiddenByUsers(): HasMany
    {
        return $this->hasMany(ChatHiddenMessage::class, 'chat_message_id');
    }

    public function forwards(): HasMany
    {
        return $this->hasMany(ForwardingChatMessage::class, 'original_message_id');
    }

    public function forward(): HasOne
    {
        return $this->hasOne(ForwardingChatMessage::class, 'forwarded_message_id');
    }

    public function unreadMessages(): HasMany
    {
        return $this->hasMany(UnreadChatMessage::class, 'chat_message_id');
    }

    public function isReadByUser(?int $userId = null): bool
    {
        if ($userId === null) {
            return false;
        }

        return ! $this->unreadMessages()->where('user_id', $userId)->exists();
    }
}
