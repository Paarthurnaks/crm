<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Comment",
 *     title="Comment",
 *     description="Комментарий",
 *     type="object",
 *
 *     @OA\Xml(
 *         name="Comment"
 *     ),
 *
 *     @OA\Property(
 *        property="id",
 *        type="int",
 *        title="id",
 *        description="идентификатор комментария",
 *        example=1
 *     ),
 *     @OA\Property(
 *        property="comment",
 *        type="string",
 *        title="comment",
 *        description="комментарий",
 *        example="242_test comment"
 *     ),
 *     @OA\Property(
 *        property="user",
 *        type="string",
 *        title="user",
 *        ref="#/components/schemas/User"
 *     ),
 *     @OA\Property(
 *        property="created_at",
 *        type="string",
 *        title="created_at",
 *        description="когда создан",
 *        example="2024-02-01T10:11:27.000000Z"
 *     ),
 *     @OA\Property(
 *        property="updated_at",
 *        type="string",
 *        title="updated_at",
 *        description="когда обновлен",
 *        example="2024-02-01T10:11:27.000000Z"
 *     )
 *
 * )
 */
class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comment' => $this->comment,
            'attachments' => MediaResource::collection($this->getMedia()),
            'user' => UserResource::make($this->commentator),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
