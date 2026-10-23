<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatCategoryResource;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class ChatCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/chat-categories",
     *     tags={"ChatCategory"},
     *     description="Метод для получения категорий чатов",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/ChatCategoryResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Unauthenticated."
     *             )
     *         )
     *     )
     * )
     */
    public function __invoke(): JsonResponse
    {
        $categories = auth()->user()->chatCategories->unique('name');

        return ResponseService::success(
            ChatCategoryResource::collection($categories)
        );
    }
}
