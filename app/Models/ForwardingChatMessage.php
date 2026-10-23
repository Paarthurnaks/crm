<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     title="ForwardingChatMessage",
 *     description="Модель пересылаемого сообщения",
 *
 *     @OA\Xml(name="ForwardingChatMessage"),
 *
 *     @OA\Property(
 *         property="id",
 *         title="ID",
 *         description="Идентификатор записи о пересылке",
 *         format="int64",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="original_message_id",
 *         title="Original Message ID",
 *         description="ID оригинального пересылаемого сообщения",
 *         format="int64",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="forwarded_message_id",
 *         title="Forwarded Message ID",
 *         description="ID пересланного сообщения",
 *         format="int64",
 *         type="integer",
 *         example=2
 *     )
 * )
 */
class ForwardingChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_message_id',
        'forwarded_message_id',
    ];

    public function originalMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'original_message_id');
    }
}
