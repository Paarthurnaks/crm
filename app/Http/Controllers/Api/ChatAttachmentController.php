<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatMessageResource;
use App\Services\ChatAttachmentService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class ChatAttachmentController extends Controller
{
    public function __construct(
        private readonly ChatAttachmentService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/chats/{id}/attachments/images",
     *     tags={"ChatAttachments"},
     *     summary="Get all media images for a chat",
     *     description="Retrieve all image media associated with a specific chat.",
     *     security={{ "bearer_token": {} }},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Chat ID to fetch images for",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *          name="page",
     *          in="query",
     *          required=false,
     *          description="Page number of the pagination",
     *
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *
     *      @OA\Parameter(
     *          name="limit",
     *          in="query",
     *          required=false,
     *          description="Number of items per page",
     *
     *          @OA\Schema(type="integer", default=20)
     *      ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/ChatMessageResource")
     *             ),
     *
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="count", type="integer", example=20),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total_pages", type="integer", example=5)
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page=1"),
     *                 @OA\Property(property="last", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={last_page}"),
     *                 @OA\Property(property="prev", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={prev_page}"),
     *                 @OA\Property(property="next", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={next_page}")
     *             )
     *         )
     *     )
     * )
     */
    public function getAllMediaImages(string $id): JsonResponse
    {
        $messages = $this->service->getAllChatMediaImages($id);

        return ResponseService::success(
            ChatMessageResource::collection($messages)->response()->getData(true)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/chats/{id}/attachments/documents/",
     *     tags={"ChatAttachments"},
     *     summary="Get all media documents for a chat",
     *     description="Retrieve all document media associated with a specific chat. Supports pagination.",
     *     security={{ "bearer_token": {} }},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Chat ID to fetch documents for",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number of the pagination",
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/ChatMessageResource")
     *             ),
     *
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="count", type="integer", example=20),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total_pages", type="integer", example=5)
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page=1"),
     *                 @OA\Property(property="last", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={last_page}"),
     *                 @OA\Property(property="prev", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={prev_page}"),
     *                 @OA\Property(property="next", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={next_page}")
     *             )
     *         )
     *     )
     * )
     */
    public function getAllMediaDocuments(string $id): JsonResponse
    {
        $messages = $this->service->getAllChatMediaDocuments($id);

        return ResponseService::success(
            ChatMessageResource::collection($messages)->response()->getData(true)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/chats/{id}/attachments/links",
     *     tags={"ChatAttachments"},
     *     summary="Get all links from chat messages",
     *     description="Retrieve all links contained within the messages of a specific chat.",
     *     security={{ "bearer_token": {} }},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Chat ID to fetch links for",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *          name="page",
     *          in="query",
     *          required=false,
     *          description="Page number of the pagination",
     *
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *
     *      @OA\Parameter(
     *          name="limit",
     *          in="query",
     *          required=false,
     *          description="Number of items per page",
     *
     *          @OA\Schema(type="integer", default=20)
     *      ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/ChatMessageResource")
     *             ),
     *
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="count", type="integer", example=20),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total_pages", type="integer", example=5)
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page=1"),
     *                 @OA\Property(property="last", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={last_page}"),
     *                 @OA\Property(property="prev", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={prev_page}"),
     *                 @OA\Property(property="next", type="string", format="uri", example="http://api.example.com/api/chats/{id}/attachments/documents?page={next_page}")
     *             )
     *         )
     *     )
     * )
     */
    public function getAllLinks(string $id): JsonResponse
    {
        $messages = $this->service->getAllChatLinks($id);

        return ResponseService::success(
            ChatMessageResource::collection($messages)->response()->getData(true)
        );
    }
}
