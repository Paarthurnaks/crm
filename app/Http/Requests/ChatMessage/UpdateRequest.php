<?php

namespace App\Http\Requests\ChatMessage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class UpdateRequest extends FormRequest
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
     *     schema="UpdateChatMessageRequest",
     *     title="UpdateChatMessageRequest",
     *     description="Request schema for updating a chat message",
     *     required={"text"},
     *
     *     @OA\Property(
     *         property="text",
     *         type="string",
     *         description="The updated text of the message",
     *         example="Updated message text"
     *     ),
     * )
     */
    public function rules(): array
    {
        return [
            'text' => ['sometimes', 'required', 'string'],
        ];
    }
}
