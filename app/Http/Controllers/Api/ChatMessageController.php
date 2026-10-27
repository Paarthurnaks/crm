<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatMessage\ForwardMessageRequest;
use App\Http\Requests\ChatMessage\IndexRequest;
use App\Http\Requests\ChatMessage\StoreRequest;
use App\Http\Requests\ChatMessage\UpdateRequest;
use App\Http\Requests\ChatMessage\UpdateStatusRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Services\ChatMessageService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatMessageController extends Controller
{
    public function __construct(
        private readonly ChatMessageService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/chats/{chatId}/messages",
     *     operationId="getChatMessages",
     *     tags={"ChatMessages"},
     *     summary="Получает список сообщений в чате",
     *     description="Возвращает список сообщений для указанного чата.",
     *
     *     @OA\Parameter(
     *         name="chatId",
     *         in="path",
     *         required=true,
     *         description="Идентификатор чата",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="direction",
     *         in="query",
     *         description="Направление загрузки сообщений",
     *         required=false,
     *
     *         @OA\Schema(
     *             type="string",
     *             enum={"upward", "downward", "both"}
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="last_message_id",
     *         in="query",
     *         description="Идентификатор последнего сообщения для пагинации",
     *         required=false,
     *
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         example=123
     *     ),
     *
     *     @OA\Parameter(
     *         name="search_term",
     *         in="query",
     *         description="Строка поиска для фильтрации сообщений",
     *         required=false,
     *
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         example="Привет"
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Список сообщений получен успешно",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/ChatMessageResource")
     *         )
     *     )
     * )
     */
    public function index(IndexRequest $request, string $chatId): JsonResponse
    {
        $validated = $request->validated();

        $messages = $this->service->getMessagesList($chatId, $validated, $request->user());

        return ResponseService::success(
            $messages
        );
    }

    /**
     * @OA\Post(
     *     path="/api/chats/{chatId}/messages",
     *     operationId="storeChatMessage",
     *     tags={"ChatMessages"},
     *     summary="Создает новое сообщение в чате",
     *     description="Добавляет новое сообщение в указанный чат.",
     *
     *     @OA\Parameter(
     *         name="chatId",
     *         in="path",
     *         required=true,
     *         description="Идентификатор чата",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Данные нового сообщения",
     *
     *         @OA\JsonContent(ref="#/components/schemas/StoreChatMessageRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Сообщение успешно создано",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ChatMessageResource")
     *     )
     * )
     */
    public function store(StoreRequest $request, string $chatId): JsonResponse
    {
        $validated = $request->validated();

        try {
            $message = $this->service->sendMessage($validated, $chatId, $request->user());

            return ResponseService::success(
                new ChatMessageResource($message, auth()->id())
            );
        } catch (\Throwable $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/chats/messages/{messageId}",
     *     operationId="showChatMessage",
     *     tags={"ChatMessages"},
     *     summary="Получает информацию о сообщении",
     *     description="Возвращает детальную информацию о конкретном сообщении в чате.",
     *
     *     @OA\Parameter(
     *         name="messageId",
     *         in="path",
     *         required=true,
     *         description="Идентификатор сообщения",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Информация о сообщении получена успешно",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ChatMessageResource")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Сообщение не найдено",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Сообщение не найдено."
     *             )
     *         )
     *     )
     * )
     */
    public function show(string $messageId): JsonResponse
    {
        $message = ChatMessage::find($messageId);

        if (! $message) {
            return ResponseService::notFound(
                message: __('Сообщение не найдено.')
            );
        }

        return ResponseService::success(
            ChatMessageResource::make($message)
        );
    }

    /**
     * Update the specified resource in storage.
     *
     *
     * @OA\Put(
     *     path="/api/chats/messages/{messageId}",
     *     summary="Update a chat message",
     *     tags={"ChatMessages"},
     *
     *     @OA\Parameter(
     *         name="messageId",
     *         in="path",
     *         required=true,
     *         description="ID of the message to update",
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data for updating the message",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UpdateChatMessageRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ChatMessageResource")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Сообщение не найдено."
     *             )
     *         )
     *     )
     * )
     */
    public function update(UpdateRequest $request, string $messageId): JsonResponse
    {
        $message = ChatMessage::find($messageId);

        if (! $message) {
            return ResponseService::notFound(
                message: __('Сообщение не найдено.')
            );
        }

        $validated = $request->validated();

        try {
            $this->service->updateMessage($validated, $message, $request->user());

            return ResponseService::success(
                ChatMessageResource::make($message)
            );
        } catch (\Throwable $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     *
     * @OA\Delete(
     *     path="/api/chats/messages/{messageId}",
     *     summary="Delete a chat message",
     *     tags={"ChatMessages"},
     *
     *     @OA\Parameter(
     *         name="messageId",
     *         in="path",
     *         required=true,
     *         description="ID of the message to delete",
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  example="Сообщение удалено."
     *              )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Сообщение не найдено."
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, string $messageId): JsonResponse
    {
        $message = ChatMessage::find($messageId);

        if (! $message) {
            return ResponseService::notFound(
                message: __('Сообщение не найдено.')
            );
        }

        $this->service->destroy($message, $request->user());

        return ResponseService::success();
    }

    /**
     * @OA\Put(
     *     path="/api/chats/{chatId}/messages/status",
     *     tags={"ChatMessages"},
     *     summary="Update message status",
     *     description="Update the status of multiple chat messages in a specific chat.",
     *
     *     @OA\Parameter(
     *         name="chatId",
     *         in="path",
     *         description="ID of the chat",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="uuid"
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Request body for updating message status",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UpdateStatusRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ChatMessageResource")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *
     *         @OA\JsonContent(
     *              type="object",
     *
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  example="Сообщение не найдено."
     *              )
     *         )
     *     )
     * )
     */
    public function updateMessageStatus(UpdateStatusRequest $request, string $chatId): JsonResponse
    {
        $validated = $request->validated();

        if (! ChatMember::query()
            ->where('chat_id', $chatId)
            ->where('user_id', $request->user()->id)
            ->exists()) {
            Log::warning('Chat message status denied', ['chat_id' => $chatId, 'actor_id' => $request->user()->id]);

            return ResponseService::forbidden(message: 'Нет доступа к этому чату');
        }

        $messages = $this->service->updateMessageStatus($validated, $chatId, $request->user());

        return ResponseService::success(
            $messages->map(function ($message) {
                return new ChatMessageResource($message, auth()->id());
            })
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/chats/messages/{messageId}/hide",
     *     tags={"ChatMessages"},
     *     summary="Hide message",
     *     description="Hide a specific chat message.",
     *
     *     @OA\Parameter(
     *         name="messageId",
     *         in="path",
     *         description="ID of the message",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *
     *     @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/ChatMessageResource")
     *      ),
     * )
     */
    public function hideMessage(Request $request, string $messageId): JsonResponse
    {
        $message = ChatMessage::find($messageId);

        if (! $message) {
            return ResponseService::notFound(
                message: __('Сообщение не найдено.')
            );
        }

        $this->service->hideMessage($message->id, $request->user());

        return ResponseService::success();
    }

    /**
     * @OA\Post(
     *     path="/api/chats/messages/forward",
     *     tags={"ChatMessages"},
     *     summary="Forward message",
     *     description="Forward a message to one or more chats.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Request body for forwarding a message",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ForwardMessageRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ChatMessageResource")
     *     )
     * )
     */
    public function forwardMessage(ForwardMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $messages = $this->service->forwardMessage($validated, $request->user());

            return ResponseService::success(
                $messages->map(function ($message) {
                    return new ChatMessageResource($message, auth()->id());
                })
            );
        } catch (\Exception $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }

    }
}
