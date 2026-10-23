<?php

namespace App\Http\Resources;

use App\Enums\DiskEntityTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DiskEntityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $res = [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->label(), // check $casts
            'who_created' => UserResource::make($this->whoCreated),
            'who_changed' => UserResource::make($this->whoChanged),
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'path' => $this->path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->type->value === DiskEntityTypeEnum::FILE->value && ! $this->deleted) {
            $res['storage_path'] = asset(Storage::url('entities/available'."$this->path/$this->title"));
        }

        return $res;
    }
}
