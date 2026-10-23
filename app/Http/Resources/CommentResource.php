<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Comment",
 *     title="Comment",
 *     description="Комментарий",
 *     type="object",
 *     @OA\Xml(
 *         name="Comment"
 *     ),
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
        $user = User::find($this->user_id);
        return [
            "id" => $this->id,
            "comment" => $this->comment,
            "attachments" => $this->resource?->getMedia() ? MediaResource::collection($this->resource->getMedia()) : null,
            "user" => UserResource::make($user),
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at
        ];
    }
}
