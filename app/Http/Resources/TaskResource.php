<?php

namespace App\Http\Resources;

use App\Enums\TaskTypeEnum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attachments = collect($this->getMedia())->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'url' => $attachment->original_url,
            ];
        });

        $res = [
            'id' => $this->id,
            'deal' => DealResource::make($this->deal),
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'attachments' => $attachments,
            'status' => $this->status,
            'creator' => UserResource::make($this->creator),
            'executors' => UserResource::collection($this->executors),
            'observers' => UserResource::collection($this->observers),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->type === TaskTypeEnum::DOCKET->value || $this->type === TaskTypeEnum::STANDARD->value) {
            $res['deadline'] = $this->deadline;
            $res['start_time'] = $this->start_time;
            $res['expired'] = $this->deadline ? Carbon::now() > Carbon::parse($this->deadline) : false;
        } elseif ($this->type === TaskTypeEnum::HELPDESK->value) {
            $res['docket_number'] = $this->docket_number;
            $res['email'] = $this->email;
            $res['client_full_name'] = $this->client_full_name;
            $res['service'] = $this->service;
        }

        return $res;
    }
}
