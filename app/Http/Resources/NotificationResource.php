<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use OpenApi\Attributes as OA;

class NotificationResource extends JsonResource
{

    #[OA\Schema(
        schema: 'NotificationResource',
        type: 'object',
        properties: [
            new OA\Property(
                property: 'id',
                type: 'string',
                example: '6051fc07-b77e-4132-9cc2-c63ea3667fbf'
            ),
            new OA\Property(
                property: 'type',
                type: 'string',
                example: 'App\Notifications\CommentNotification'
            ),
            new OA\Property(
                property: 'data',
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'item',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'id',
                                type: 'integer',
                                example: 23
                            ),
                            new OA\Property(
                                property: 'comment',
                                type: 'string',
                                example: '242_test'
                            ),
                            new OA\Property(
                                property: 'created_at',
                                type: 'string',
                                example: '2024-04-08T12:08:17.000000Z'
                            ),
                            new OA\Property(
                                property: 'updated_at',
                                type: 'string',
                                example: '2024-04-08T12:08:17.000000Z'
                            )
                        ]
                    )
                ]
            ),
            new OA\Property(
                property: 'read_at',
                type: 'string',
                format: 'date-time',
                nullable: true
            ),
            new OA\Property(
                property: 'created_at',
                type: 'string',
                format: 'date-time',
                example: '2024-04-08T12:08:17.000000Z'
            )
        ]
    )]

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data' => $this->data,
            'read_at' => $this->read_at,
        ];
    }
}
