<?php

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChatAttachmentService
{
    /**
     * Retrieve media based on MIME type pattern and chat ID.
     *
     * @param  int  $id  Chat ID
     * @param  string  $mimeType  MIME type to filter by
     * @param  string  $operator  The operator for the MIME type comparison
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function getMediaByType(int $id, string $mimeType, ?int $limit = null, string $operator = 'like'): LengthAwarePaginator
    {
        // Media модель из media-library не робит для получения, так что пишем костыль
        $chatMediaMessages = DB::table('media')
            ->where('model_type', ChatMessage::class)
            ->where('custom_properties->chat_id', $id)
            ->where('mime_type', $operator, $mimeType)
            ->orderByDesc('created_at')
            ->paginate($limit ?? config('chats.attachments_limit'));

        $chatMediaMessages->getCollection()->transform(function ($media) {
            $mediaModel = Media::find($media->id);

            return $mediaModel ? $mediaModel->model : null;
        });

        Log::debug('chat.attachments.loaded', ['chat_id' => $id, 'count' => $chatMediaMessages->count()]);

        return $chatMediaMessages;
    }

    /**
     * Get all media that are images for a specific chat.
     *
     * @param  int  $id  Chat ID
     */
    public function getAllChatMediaImages(int $id, ?int $limit = null): LengthAwarePaginator
    {
        return $this->getMediaByType($id, 'image/%', $limit);
    }

    /**
     * Get all media that are documents for a specific chat.
     *
     * @param  int  $id  Chat ID
     */
    public function getAllChatMediaDocuments(int $id, ?int $limit = null): LengthAwarePaginator
    {
        return $this->getMediaByType($id, 'image/%', $limit, 'not like');
    }

    public function getAllChatLinks(int $id): LengthAwarePaginator
    {
        return ChatMessage::where('chat_id', $id)
            ->where('text', 'REGEXP', '\\b(?:https?://|www\\.)\\S+\\b')
            ->orderByDesc('created_at')
            ->paginate(config('chats.attachments_limit'));
    }
}
