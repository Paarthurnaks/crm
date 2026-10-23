<?php

namespace App\Http\Requests\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;

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
     *     schema="KnowledgeBaseUpdateRequest",
     *     type="object",
     *     title="Запрос на обновление базы знаний",
     *
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
     *
     *         @OA\Items(
     *             type="object",
     *
     *              @OA\Property(
     *                  property="id",
     *                  type="integer",
     *                  description="ID блока",
     *                  nullable=true
     *              ),
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
            'title' => ['sometimes', 'string', 'max:255'],
            'preview' => ['sometimes', 'nullable', 'file', 'mimetypes:image/*'],
            'description' => ['sometimes', 'nullable', 'string'],
            'contents' => ['sometimes', 'array'],
            'contents.*.id' => ['sometimes', 'integer', 'exists:knowledge_base_contents,id'],
            'contents.*.content' => [
                'required_without_all:contents.*.file,contents.*.id',
                'string',
                'nullable',
            ],
            'contents.*.file' => [
                'required_without_all:contents.*.content,contents.*.id',
                'file',
                'nullable',
            ],
        ];
    }
}
