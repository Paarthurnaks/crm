<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Division extends Model
{
    use HasFactory;

    const COMMON = 1;

    protected $fillable = [
        "title",
        "parent_id"
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Division::class, "parent_id");
    }
}
