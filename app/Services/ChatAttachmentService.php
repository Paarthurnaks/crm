<?php

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChatAttachmentService
{
    /**
     * Retrieve media based on MIME type pattern and chat ID.
     *
     * @param int $id Chat ID
     * @param string $mimeType MIME type to filter by
     * @param string $operator The operator for the MIME type comparison
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function getMediaByType(int $id, string $mimeType, string $operator = 'like'): LengthAwarePaginator
    {
        // Media модель из media-library не робит для получения, так что пишем костыль
        $chatMediaMessages = DB::table('media')
            ->where('model_type', ChatMessage::class)
            ->where('custom_properties->chat_id', $id)
            ->where('mime_type', $operator, $mimeType)
            ->orderByDesc('created_at')
            ->paginate(request()->query('limit') ?? config('chats.attachments_limit'));

        $chatMediaMessages->getCollection()->transform(function ($media) {
            $mediaModel = Media::find($media->id);
            return $mediaModel ? $mediaModel->model : null;
        });

        return $chatMediaMessages;
    }

    /**
     * Get all media that are images for a specific chat.
     *
     * @param int $id Chat ID
     * @return LengthAwarePaginator
     */
    public function getAllChatMediaImages(int $id): LengthAwarePaginator
    {
        return $this->getMediaByType($id, 'image/%');
    }

    /**
     * Get all media that are documents for a specific chat.
     *
     * @param int $id Chat ID
     * @return LengthAwarePaginator
     */
    public function getAllChatMediaDocuments(int $id): LengthAwarePaginator
    {
        return $this->getMediaByType($id, 'image/%', 'not like');
    }

    /**
     * @param int $id
     * @return LengthAwarePaginator
     */
    public function getAllChatLinks(int $id): LengthAwarePaginator
    {
        return ChatMessage::where('chat_id', $id)
            ->where('text', 'REGEXP', '\\b(?:https?://|www\\.)\\S+\\b')
            ->orderByDesc('created_at')
            ->paginate(config('chats.attachments_limit'));
    }
}
