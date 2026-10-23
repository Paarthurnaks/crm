<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeBaseContentResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="KnowledgeBaseContentResource",
     *     type="object",
     *     title="Ресурс контента базы знаний",
     *     @OA\Property(
     *         property="type",
     *         type="string",
     *         description="Тип контента (файл или текст)"
     *     ),
     *     @OA\Property(
     *         property="file",
     *         type="string",
     *         format="url",
     *         description="URL файла, если тип контента - файл"
     *     ),
     *     @OA\Property(
     *         property="content",
     *         type="string",
     *         description="Текстовое содержимое, если тип контента - текст"
     *     ),
     *     @OA\Property(
     *         property="order",
     *         type="integer",
     *         description="Порядок контента"
     *     )
     * )
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'file' => MediaResource::make($this->getFirstMedia('file')),
            'content' => $this->content,
            'order' => $this->order,
        ];
    }
}
