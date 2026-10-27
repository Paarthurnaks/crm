<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ChatFilter;
use App\Http\Requests\Chat\IndexRequest;
use App\Http\Requests\Chat\StoreRequest;
use App\Http\Requests\Chat\UpdateRequest;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Services\ChatService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $service) {}

    /**
     * @OA\Get(
     *     path="/api/chats/",
     *     operationId="getChats",
     *     tags={"Chat"},
     *     description="Метод для получения всех чатов с пагинацией",
     *     summary="Метод для получения всех чатов с пагинацией",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="search_term",
     *          description="Строка для поиска по чатам",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *           name="category_id",
     *           description="ID категории",
     *           required=false,
     *           in="query",
     *
     *           @OA\Schema(
     *               type="integer"
     *           )
     *       ),
     *
     *     @OA\Response(
     *          response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *                  type="object",
     *
     *                  @OA\Property(
     *                      property="data",
     *                      type="array",
     *
     *                      @OA\Items(ref="#/components/schemas/Chat")
     *                  ),
     *              )
     *          )
     *     )
     * )
     */
    public function index(IndexRequest $request, ChatFilter $filter): JsonResponse
    {
        $validated = $request->all();

        $chats = $this->service->getChatsList($validated, $filter, $request->user());

        return ResponseService::success(
            ChatResource::collection($chats)->response()->getData(true)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/chats",
     *     operationId="createChat",
     *     tags={"Chat"},
     *     summary="Создает новый чат",
     *     description="Создает новый чат с указанными параметрами.",
     *          security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Данные чата для создания",
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(ref="#/components/schemas/Chat")
     *         )
     *     ),
     *
     *     @OA\Response(
     *          response=200,
     *          description="Успешное получение данных чата.",
     *
     *         @OA\JsonContent(
     *              type="object",
     *
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  ref="#/components/schemas/ChatResource"
     *              )
     *          )
     *      ),
     *
     *     @OA\Response(response="404",
     *         description="NOT FOUND"
     *     ),
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $chat = $this->service->create($validated);

            return ResponseService::created(
                ChatResource::make($chat)
            );
        } catch (\Throwable $e) {
            return ResponseService::fromException($e, 'chat.store');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/chats/{id}",
     *     operationId="showChat",
     *     tags={"Chat"},
     *     summary="Получает чат по id",
     *     description="Получает чат по id.",
     *          security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *           name="id",
     *           description="id чата",
     *           required=true,
     *           in="path",
     *
     *           @OA\Schema(
     *               type="string"
     *           )
     *       ),
     *
     *     @OA\Response(
     *          response=200,
     *          description="Успешное получение данных чата.",
     *
     *         @OA\JsonContent(
     *              type="object",
     *
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  ref="#/components/schemas/ChatResource"
     *              )
     *          )
     *      ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Чат не найден.",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Сообщение о том, что чат не найден.",
     *                 example="Чат не найден."
     *             )
     *         )
     *      )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $chat = Chat::find($id);

        if (! $chat) {
            return ResponseService::notFound(
                message: __('Чат не найден.')
            );
        }

        return ResponseService::success(
            ChatResource::make($chat)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/chats/{id}",
     *     operationId="updateChat",
     *     tags={"Chat"},
     *     summary="Обновляет чат",
     *     description="Обновляет чат.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Данные чата для создания",
     *
     *       @OA\Property(
     *          property="name",
     *          type="string",
     *          description="Имя чата",
     *          example="Название чата",
     *          nullable=true,
     *          maxLength=255
     *      ),
     *      @OA\Property(
     *          property="description",
     *          type="string",
     *          description="Описание чата",
     *          example="Описание чата, доступно до 1000 символов.",
     *          nullable=true,
     *          maxLength=1000
     *      ),
     *      @OA\Property(
     *          property="avatar",
     *          type="string",
     *          description="Ссылка на аватар чата. Поддерживаемые форматы: jpeg, jpg, png, avif, webp. Максимальный размер файла: 4096 КБ.",
     *          example="/path/to/avatar.jpg",
     *          nullable=true
     *      )
     *    ),
     *
     *     @OA\Response(
     *          response=200,
     *          description="Успешное получение данных чата.",
     *
     *         @OA\JsonContent(
     *              type="object",
     *
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  ref="#/components/schemas/ChatResource"
     *              )
     *          )
     *      ),
     *
     *     @OA\Response(response="404",
     *         description="NOT FOUND"
     *     ),
     * )
     */
    public function update(UpdateRequest $request, string $id): JsonResponse
    {
        $chat = Chat::find($id);

        if (! $chat) {
            return ResponseService::notFound(
                message: __('Чат не найден.')
            );
        }

        $validated = $request->validated();

        try {
            $updatedChat = $this->service->update($chat, $validated);

            return ResponseService::success(
                ChatResource::make($updatedChat)
            );
        } catch (\Throwable $e) {
            return ResponseService::fromException($e, 'chat.update');
        }

    }

    /**
     * @OA\Delete (
     *     path="/api/chats/{id}",
     *     operationId="deleteChat",
     *     tags={"Chat"},
     *     summary="Удаляет чат по id",
     *     description="Удаляет чат по id.",
     *
     *     @OA\Parameter(
     *           name="id",
     *           description="id чата",
     *           required=true,
     *           in="path",
     *
     *           @OA\Schema(
     *               type="string"
     *           )
     *       ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Чат успешно удален.",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Сообщение об успешном удалении чата.",
     *                 example="Чат удален."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Чат не найден.",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Сообщение о том, что чат не найден.",
     *                 example="Чат не найден."
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $chat = Chat::find($id);

        if (! $chat) {
            return ResponseService::notFound(message: __('Чат не найден.'));
        }

        try {
            $this->service->delete($chat);

            return ResponseService::success(message: __('Чат удален.'));

        } catch (\Throwable $e) {
            return ResponseService::fromException($e, 'chat.destroy');
        }

    }
}
