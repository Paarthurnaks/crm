<?php

namespace App\Http\Requests\ChatMember;

use App\Models\ChatMember;
use App\Rules\UniqueChatMember;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    private $chatId;

    public function prepareForValidation()
    {
        $this->chat = $this->route('chatId');
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @OA\Schema(
     *     schema="StoreChatMemberRequest",
     *     type="object",
     *     required={"user_ids"},
     *     @OA\Property(
     *         property="user_ids",
     *         type="array",
     *         @OA\Items(
     *             type="integer",
     *             description="Идентификатор пользователя",
     *         ),
     *         description="Массив идентификаторов пользователей для добавления в чат.",
     *         minItems=1,
     *     ),
     * )
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'distinct', 'integer', 'exists:users,id',
                function ($attribute, $value, $fail) {
                    if (ChatMember::where('chat_id', $this->chatId)->where('user_id', $value)->exists()) {
                        $fail('Один или несколько указанных пользователей уже являются участниками чата.');
                    }
                },
            ],
        ];
    }
}
