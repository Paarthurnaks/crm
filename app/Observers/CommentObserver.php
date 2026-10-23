<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Deal;
use App\Models\EntityActivity;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CommentNotification;

class CommentObserver
{
    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        if ($comment->commentable_type === Deal::class) {
            $deal = $comment->commentable;

            $userId = $deal->creator_id !== auth()->id() ? $deal->creator_id : $deal->responsible_id;

            User::find($userId)->notify(new CommentNotification($comment, $deal));

            EntityActivity::create([
                'entity_id' => $comment->commentable_id,
                'entity_type' => Deal::class,
                'activityable_type' => Comment::class,
                'activityable_id' => $comment->id,
            ]);
        }

        if ($comment->commentable_type === Task::class) {
            $task = $comment->commentable;

            $task->executors->each(function ($executor) use ($comment, $task) {
                $executor->notify(new CommentNotification($comment, $task));
            });
        }

    }

    public function deleted(Comment $comment): void
    {
        if ($comment->commentable_type === Deal::class) {
            EntityActivity::where('activityable_type', Comment::class)
                ->where('activityable_id', $comment->id)
                ->delete();
        }
    }
}
