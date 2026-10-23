<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property $user_id
 * @property $chat_message_id
 *
 * @OA\Schema(
 *     title="ChatHiddenMessage",
 *     description="Модель для скрытых сообщений",
 *     @OA\Xml(name="ChatHiddenMessage"),
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
 */
class ChatHiddenMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chat_message_id',
    ];

}
