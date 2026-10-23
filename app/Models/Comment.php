<?php

namespace App\Models;

use BeyondCode\Comments\Comment as BaseComment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Comment extends BaseComment implements HasMedia
{
    use HasFactory, InteractsWithMedia;
}
