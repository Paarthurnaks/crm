<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeBaseResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="KnowledgeBaseResource",
     *     type="object",
     *     title="Ресурс базы знаний",
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         description="Идентификатор базы знаний"
     *     ),
     *     @OA\Property(
     *         property="title",
     *         type="string",
     *         description="Название базы знаний"
     *     ),
     *     @OA\Property(
     *         property="description",
     *         type="string",
     *         description="Описание базы знаний"
     *     ),
     *     @OA\Property(
     *         property="preview",
     *         type="string",
     *         format="url",
     *         description="URL превью изображения"
     *     ),
     *     @OA\Property(
     *         property="contents",
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/KnowledgeBaseContentResource")
     *     )
     * )
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'preview' => $this->getFirstMedia('preview')?->getUrl(),
            'contents' => KnowledgeBaseContentResource::collection($this->contents),
        ];
    }
}
