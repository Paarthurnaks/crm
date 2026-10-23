<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\IndexRequest;
use App\Http\Requests\Notification\MarkAsReadRequest;
use App\Services\NotificationService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;


class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    )
    {
    }



    #[OA\Get(
        path: '/api/notifications',
        tags: ['Notifications'],
        summary: 'Получить список уведомлений',
        description: 'Возвращает список уведомлений для авторизованного пользователя',
        parameters: [
            new OA\Parameter(
                name: 'date',
                in: 'query',
                description: 'Фильтр уведомлений по дате в формате YYYY-MM-DD',
                required: false,
                example: '2023-01-01',
                schema: new OA\Schema(
                    type: 'string',
                    format: 'date'
                )
            ),
            new OA\Parameter(
                name: 'search_term',
                in: 'query',
                description: 'Строка поиска для фильтрации уведомлений',
                required: false,
                example: 'Важное',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'notifications',
                            type: 'array',
                            description: 'List of notifications',
                            items: new OA\Items(ref: '#/components/schemas/NotificationResource')
                        ),
                        new OA\Property(
                            property: 'total_unread_notifications',
                            type: 'integer',
                            description: 'Total number of unread notifications'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ],
        security: [['bearer_token' => []]]
    )]

    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $notifications = $this->service->getNotifications($validated);

        return ResponseService::success(
            $notifications
        );
    }

    #[OA\Post(
        path: '/api/notifications/read',
        tags: ['Notifications'],
        summary: 'Отметить уведомления как прочитанные',
        description: 'Отмечает уведомления как прочитанные для авторизованного пользователя',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'notification_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string', example: '443b815c-4afe-47c0-8924-0d3f4f5d160e')
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ],
        security: [['bearer_token' => []]]
    )]

    public function markAsRead(MarkAsReadRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->service->markAsRead($validated);

        return ResponseService::success();
    }

    #[OA\Delete(
        path: '/api/notifications/{id}',
        tags: ['Notifications'],
        summary: 'Удалить уведомление',
        description: 'Удаляет уведомление по идентификатору для авторизованного пользователя',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'ID уведомления для удаления',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '443b815c-4afe-47c0-8924-0d3f4f5d160e')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Уведомление успешно удалено'
            ),
            new OA\Response(
                response: 404,
                description: 'Уведомление не найдено'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ],
        security: [[ "bearer_token" => [] ]]
    )]

    public function delete($id): JsonResponse
    {
        $notification = auth()->user()
                            ->notifications()
                            ->where('id', $id)
                            ->first();

        if(!$notification) {
            return ResponseService::notFound(
                message: __('Уведомление не найдено.')
            );
        }

        $this->service->delete($notification);

        return ResponseService::success(message: __('Уведомление успешно удалено'));
    }

    #[OA\Post(
        path: '/api/notifications/read-all',
        tags: ['Notifications'],
        summary: 'Отметить все уведомления как прочитанные',
        description: 'Отмечает все уведомления как прочитанные для авторизованного пользователя',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ],
        security: [['bearer_token' => []]]
    )]

    public function markAsReadAll(): JsonResponse
    {
        $this->service->markAsReadAll();

        return ResponseService::success();
    }
}
