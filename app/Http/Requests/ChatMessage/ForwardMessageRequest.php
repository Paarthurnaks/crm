<?php

namespace App\Http\Requests\ChatMessage;

use Illuminate\Foundation\Http\FormRequest;

class ForwardMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @OA\Schema(
     *     schema="ForwardMessageRequest",
     *     title="Forward Message Request",
     *     description="Request body schema for forwarding messages to other chats.",
     *     required={"chat_ids", "message_ids"},
     *     @OA\Property(
     *         property="chat_ids",
     *         type="array",
     *         description="Array of chat IDs to forward the message(s) to",
     *         @OA\Items(
     *             type="integer",
     *             format="int64",
     *             description="ID of the chat",
     *             example=1
     *         )
     *     ),
     *     @OA\Property(
     *         property="message_ids",
     *         type="array",
     *         description="Array of message IDs to forward",
     *         @OA\Items(
     *             type="integer",
     *             format="int64",
     *             description="ID of the message",
     *             example=1
     *         )
     *     )
     * )
     */
    public function rules(): array
    {
        return [
            'chat_ids' => ['required', 'array'],
            'chat_ids.*' => ['integer', 'exists:chats,id'],
            'message_ids' => ['required', 'array'],
            'message_ids.*' => ['integer', 'exists:chat_messages,id'],
        ];
    }
}
