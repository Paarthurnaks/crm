<?php

namespace App\Services;

use App\Models\Comment;

class CommentService
{
    public function update(Comment $comment, array $data): Comment
    {
        $comment->update($data);

        return $comment;
    }

    public function delete(Comment $comment): bool|null
    {
        return $comment->delete();
    }
}
