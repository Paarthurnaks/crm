<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreRequest;
use App\Http\Requests\Account\UpdateRequest;
use App\Models\Comment;
use App\Services\CommentService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $service) {}

    public function index(): JsonResponse
    {
        return ResponseService::sendJsonResponse(
            status: 'Method not allowed',
            code: 405
        );
    }

    public function show(): JsonResponse
    {
        return ResponseService::sendJsonResponse(
            status: 'Method not allowed',
            code: 405
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return ResponseService::sendJsonResponse(
            status: 'Method not allowed',
            code: 405
        );
    }

    /**
     * @OA\Put(
     *     path="/api/comments/{id}",
     *     operationId="updateComment",
     *     tags={"Comment"},
     *     description="Метод для обновления комментария",
     *     summary="Метод для обновления комментария",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id комментария",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *              example=5984
     *          )
     *      ),
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="comment", type="string", example="some comment"),
     *           ),
     *       ),
     *     ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     type="boolean",
     *                     example=true
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $user = Auth::user();
        $comment = Comment::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $comment) {
            return ResponseService::notFound(
                message: 'Комментарий не найден'
            );
        }

        $request->validate([
            'comment' => ['required', 'string'],
        ]);

        $this->service->update($comment, [
            'comment' => $request->input('comment'),
        ]);

        return ResponseService::success(true);
    }

    /**
     * @OA\Delete(
     *     path="/api/comments/{id}",
     *     operationId="deleteComment",
     *     tags={"Comment"},
     *     description="Метод для удаления комментария",
     *     summary="Метод для удаления комментария",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id комментария",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *              example=42
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *     ),
     *     @OA\Response(response="404",
     *          description="NOT FOUND"
     *     ),
     *     @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $comment = Comment::where('user_id', $request->user()->id)
                ->where('id', $id)
                ->first();

            if (! $comment) {
                return ResponseService::notFound(
                    message: 'Комментарий не найден'
                );
            }

            $this->service->delete($comment);

            return ResponseService::success(message: 'Комментарий удален');
        } catch (\Throwable $e) {
            return ResponseService::badRequest(message: 'Не удалось удалить комментарий');
        }
    }
}
