<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="MediaResource",
     *     type="object",
     *     title="Chat Media Resource",
     *     description="Represents a media attachment in the chat message.",
     *
     *     @OA\Property(
     *         property="file_name",
     *         type="string",
     *         description="The name of the file."
     *     ),
     *     @OA\Property(
     *         property="url",
     *         type="string",
     *         description="The URL where the file can be accessed."
     *     ),
     *     @OA\Property(
     *         property="size",
     *         type="integer",
     *         description="The size of the file in bytes."
     *     ),
     *     @OA\Property(
     *         property="extension",
     *         type="string",
     *         description="The file extension indicating the file type."
     *     ),
     *     @OA\Property(
     *         property="mime_type",
     *         type="string",
     *         description="The file mime type indicating the file type."
     *     ),
     * )
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'url' => $this->getUrl(),
            'size' => $this->size,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
        ];
    }
}
