<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\EntityActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommentService
{
    public function update(Comment $comment, array $data): Comment
    {
        $comment->update($data);

        return $comment;
    }

    public function delete(Comment $comment): ?bool
    {
        Log::debug('comment.delete.started', ['comment_id' => $comment->id]);

        try {
            return DB::transaction(function () use ($comment): ?bool {
                $deleted = $comment->delete();
                if ($deleted) {
                    EntityActivity::where([
                        'entity_type' => $comment->commentable_type,
                        'entity_id' => $comment->commentable_id,
                        'activityable_type' => $comment->getMorphClass(),
                        'activityable_id' => $comment->id,
                    ])->delete();
                }

                return $deleted;
            });
        } catch (\Throwable $exception) {
            Log::error('comment.delete.failed', ['comment_id' => $comment->id, 'exception' => $exception::class]);
            throw $exception;
        }
    }
}
