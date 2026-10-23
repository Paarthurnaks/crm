<?php

namespace App\Http\Requests\ChatMessage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreRequest extends FormRequest
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
     *     schema="StoreChatMessageRequest",
     *     title="Store Chat Message Request",
     *     description="Represents the request body for creating a new chat message.",
     *     required={"text"},
     *     @OA\Property(
     *          property="text",
     *          type="string",
     *          description="The text content of the message. Required if no attachments are provided."
     *      ),
     *      @OA\Property(
     *          property="reply_message_id",
     *          type="integer",
     *          description="Optional ID of the message being replied to.",
     *          nullable=true
     *      ),
     *      @OA\Property(
     *          property="attachments",
     *          type="array",
     *          description="Optional array of attachments. Required if no text content is provided. Maximum of 10 attachments allowed.",
     *          @OA\Items(type="string", format="binary"),
     *          @OA\ExternalDocumentation(
     *              description="Note",
     *              url="Note: Each attachment must be a file of type jpg, jpeg, png, webp, avif, pdf, or txt."
     *          )
     *      )
     * )
     */
    public function rules(Request $request): array
    {
        return [
            'text' => ['nullable', 'required_without:attachments', 'string'],
            'reply_message_id' => ['nullable', 'integer', 'exists:chat_messages,id'],
            'attachments' => ['nullable', 'required_without:text', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,avif,pdf,txt'],
        ];
    }
}
