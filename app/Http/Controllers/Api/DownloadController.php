<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatHiddenMessage;
use App\Models\ChatMessage;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/download/{id}",
     *     summary="Скачать медиафайл по ID",
     *     description="Возвращает медиафайл для скачивания по указанному ID.",
     *     operationId="downloadMedia",
     *     tags={"Download"},
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID медиафайла",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Файл найден и возвращен для скачивания",
     *
     *         @OA\MediaType(
     *              mediaType="application/octet-stream",
     *
     *              @OA\Schema(
     *                  type="string",
     *                  format="binary"
     *              )
     *          )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Ничего не найдено",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ничего не найдено."),
     *             @OA\Property(property="status", type="string", example="error")
     *         )
     *     )
     * )
     */
    public function __invoke(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $media = Media::find($id);

        if (! $media) {
            return ResponseService::badRequest(message: 'Ничего не найдено.');
        }

        if ($media->model_type === Chat::class && ! $request->user()->isMemberOfChat($media->model_id)) {
            return ResponseService::forbidden();
        }

        if ($media->model_type === ChatMessage::class) {
            $message = ChatMessage::withoutGlobalScopes()->find($media->model_id);
            $hidden = ChatHiddenMessage::where('chat_message_id', $media->model_id)
                ->where('user_id', $request->user()->id)
                ->exists();
            if (! $message || ! $request->user()->isMemberOfChat($message->chat_id) || $hidden) {
                return ResponseService::forbidden();
            }
        }

        return Response::download($media->getPath(), $media->file_name);
    }
}
