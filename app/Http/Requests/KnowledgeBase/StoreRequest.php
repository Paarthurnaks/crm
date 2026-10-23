<?php

namespace App\Http\Requests\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;

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
     *     schema="KnowledgeBaseStoreRequest",
     *     type="object",
     *     title="Запрос на создание базы знаний",
     *     required={"title", "preview", "contents"},
     *     @OA\Property(
     *         property="title",
     *         type="string",
     *         description="Название базы знаний"
     *     ),
     *     @OA\Property(
     *         property="preview",
     *         type="string",
     *         format="binary",
     *         description="Файл превью"
     *     ),
     *     @OA\Property(
     *         property="description",
     *         type="string",
     *         description="Описание базы знаний"
     *     ),
     *     @OA\Property(
     *         property="contents",
     *         type="array",
     *         @OA\Items(
     *             type="object",
     *             @OA\Property(
     *                 property="content",
     *                 type="string",
     *                 description="Текстовое содержимое"
     *             ),
     *             @OA\Property(
     *                 property="file",
     *                 type="string",
     *                 format="binary",
     *                 description="Файл содержимого"
     *             )
     *         )
     *     )
     * )
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'preview' => ['sometimes', 'file', 'mimetypes:image/*'],
            'description' => ['sometimes', 'string'],
            'contents' => ['required', 'array'],
            'contents.*.content' => ['required_without:contents.*.file', 'string'],
            'contents.*.file' => ['required_without:contents.*.content', 'file'],
        ];
    }
}
