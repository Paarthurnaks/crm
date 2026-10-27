<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\EntityActivity;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentDeletionActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_comment_removes_its_activity(): void
    {
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User',
            'email' => 'comment-delete@example.test', 'password' => 'unused',
        ]));
        $comment = new Comment;
        $comment->forceFill([
            'commentable_type' => User::class,
            'commentable_id' => $user->id,
            'comment' => 'Test comment',
            'user_id' => $user->id,
        ]);
        $comment->save();
        $activity = EntityActivity::create([
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'activityable_type' => $comment->getMorphClass(),
            'activityable_id' => $comment->id,
        ]);

        app(CommentService::class)->delete($comment);

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('entity_activities', ['id' => $activity->id]);
    }

    public function test_other_users_comment_is_not_deleted(): void
    {
        $owner = User::withoutEvents(fn (): User => User::create([
            'name' => 'Owner', 'surname' => 'User',
            'email' => 'comment-owner@example.test', 'password' => 'unused',
        ]));
        $other = User::withoutEvents(fn (): User => User::create([
            'name' => 'Other', 'surname' => 'User',
            'email' => 'comment-other@example.test', 'password' => 'unused',
        ]));
        $comment = new Comment;
        $comment->forceFill([
            'commentable_type' => User::class,
            'commentable_id' => $owner->id,
            'comment' => 'Private comment',
            'user_id' => $owner->id,
        ]);
        $comment->save();

        $this->actingAs($other, 'api')->deleteJson('/api/comments/'.$comment->id)->assertNotFound();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }
}
