<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     title="ChatHiddenMessage",
 *     description="Модель для скрытых сообщений",
 *
 *     @OA\Xml(name="ChatHiddenMessage"),
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
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="chat_message_id",
 *         title="Chat Message ID",
 *         description="Идентификатор сообщения в чате",
 *         format="int64",
 *         type="integer",
 *         example=1
 *     )
 * )
 *
 * @property int $id
 * @property int $user_id
 * @property int $chat_message_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ChatHiddenMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chat_message_id',
    ];
}
