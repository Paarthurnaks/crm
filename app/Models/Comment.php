<?php

namespace App\Models;

use BeyondCode\Comments\Comment as BaseComment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property string $comment
 * @property bool $is_approved
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $commentable
 * @property-read \App\Models\User|null $commentator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @method static \Illuminate\Database\Eloquent\Builder<static> approved()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Comment extends BaseComment implements HasMedia
{
    use HasFactory, InteractsWithMedia;
}
