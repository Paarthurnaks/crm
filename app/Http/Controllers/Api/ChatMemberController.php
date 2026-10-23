<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatMember\StoreRequest;
use App\Http\Resources\ChatMemberResource;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Services\ChatMemberService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMemberController extends Controller
{
    public function __construct(private readonly ChatMemberService $service)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/api/chats/{chatId}/members",
     *     operationId="getChatMembers",
     *     tags={"ChatMembers"},
     *     summary="Получить список участников чата",
     *     description="Возвращает список участников чата по указанному идентификатору чата.",
     *     @OA\Parameter(
     *         name="chatId",
     *         in="path",
     *         required=true,
     *         description="Идентификатор чата",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список участников чата успешно получен",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         description="ID участника чата"
     *                     ),
     *                     @OA\Property(
     *                         property="chat",
     *                         type="object",
     *                         description="Информация о чате",
     *                         ref="#/components/schemas/ChatResource"
     *                     ),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         description="Информация о пользователе",
     *                         ref="#/components/schemas/UserResource"
     *                     ),
     *                     @OA\Property(
     *                         property="is_creator",
     *                         type="boolean",
     *                         description="Является ли пользователь создателем чата"
     *                     ),
     *                     @OA\Property(
     *                         property="created_at",
     *                         type="string",
     *                         format="date-time",
     *                         description="Дата и время создания записи участника чата"
     *                     ),
     *                     @OA\Property(
     *                         property="updated_at",
     *                         type="string",
     *                         format="date-time",
     *                         description="Дата и время последнего обновления записи участника чата"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Чат не найден",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Чат не найден"
     *             )
     *         )
     *     )
     * )
     */
    public function index(string $chatId): JsonResponse
    {
        $chat = Chat::find($chatId);

        if(!$chat) {
            return ResponseService::notFound(message: 'Чат не найден');
        }

        $members = $this->service->getMembers($chat);

        return ResponseService::success(
            ChatMemberResource::collection($members)
        );
    }



    /**
     * @OA\Post(
     *     path="/api/chats/{chatId}/members",
     *     operationId="addChatMember",
     *     tags={"ChatMembers"},
     *     summary="Добавляет участника в чат",
     *     description="Добавляет нового участника в указанный чат.",
     *     @OA\Parameter(
     *         name="chatId",
     *         in="path",
     *         required=true,
     *         description="Идентификатор чата",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Данные участника для добавления в чат",
     *         @OA\JsonContent(ref="#/components/schemas/StoreChatMemberRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Участник успешно добавлен в чат",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/ChatMemberResource")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка валидации данных запроса",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Чат не найден",
     *     )
     * )
     */
    public function store(StoreRequest $request, string $chatId): JsonResponse
    {
        $chat = Chat::find($chatId);

        if(!$chat) {
            return ResponseService::notFound(message: 'Чат не найден');
        }

        $validated = $request->validated();

        try {
            $members = $this->service->create($validated, $chat);

            return ResponseService::success(
                ChatMemberResource::collection($members)
            );
        } catch (\Throwable $e) {
            return ResponseService::badRequest($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/chats/members/{memberId}",
     *     operationId="showChatMember",
     *     tags={"ChatMembers"},
     *     summary="Получает информацию об участнике чата",
     *     description="Возвращает информацию об участнике чата по идентификатору участника.",
     *     @OA\Parameter(
     *         name="memberId",
     *         in="path",
     *         required=true,
     *         description="Идентификатор участника чата",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Информация об участнике чата",
     *         @OA\JsonContent(ref="#/components/schemas/ChatMemberResource")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Участник чата не найден",
     *     )
     * )
     */
    public function show(string $memberId): JsonResponse
    {
        $member = ChatMember::find($memberId);

        if (!$member) {
            return ResponseService::notFound(
                message: __('Участник не найден.')
            );
        }

        return ResponseService::success(
            ChatMemberResource::make($member)
        );
    }


    /**
     * @OA\Delete(
     *     path="/api/chats/members/{memberId}",
     *     operationId="deleteChatMember",
     *     tags={"ChatMembers"},
     *     summary="Удаляет участника из чата",
     *     description="Удаляет участника чата по идентификатору участника.",
     *     @OA\Parameter(
     *         name="memberId",
     *         in="path",
     *         required=true,
     *         description="Идентификатор участника чата для удаления",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Участник чата успешно удален",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Пользователь удален из чата."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Участник чата не найден",
     *     )
     * )
     */
    public function destroy(string $memberId): JsonResponse
    {
        $member = ChatMember::find($memberId);

        if (!$member) {
            return ResponseService::notFound(message: __('Пользователь не найден в чате.'));
        }

        try {
            $this->service->delete($member);

            return ResponseService::success(message: __("Пользователь удален из чата."));
        } catch (\Throwable $e) {
            return ResponseService::badRequest($e->getMessage());
        }
    }
}
