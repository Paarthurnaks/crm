<?php

namespace App\Http\Requests\ChatMessage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
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
     *     schema="UpdateStatusRequest",
     *     title="Update Status Request",
     *     description="Request body schema for updating message status.",
     *     required={"message_ids"},
     *     @OA\Property(
     *         property="message_ids",
     *         type="array",
     *         description="Array of message IDs to update status",
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
            'message_ids' => ['required', 'array'],
            'message_ids.*' => ['exists:chat_messages,id'],
        ];
    }
}
