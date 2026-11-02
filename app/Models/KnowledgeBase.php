<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @OA\Schema(
 *     title="KnowledgeBase",
 *     description="База знаний",
 *
 *     @OA\Xml(
 *         name="KnowledgeBase"
 *     )
 * )
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\KnowledgeBaseContent> $contents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class KnowledgeBase extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
    ];

    /**
     * @OA\Property(
     *     title="ID",
     *     description="ID",
     *     format="int64",
     *     example=1
     * )
     */
    private int $id;

    /**
     * @OA\Property(
     *     title="title",
     *     description="title",
     *     format="string",
     *     example="my fucking title"
     * )
     */
    private string $title;

    /**
     * @OA\Property(
     *     title="description",
     *     format="string",
     *     example="my fucking description"
     * )
     */
    private string $description;

    public function contents(): HasMany
    {
        return $this->hasMany(KnowledgeBaseContent::class)->orderBy('order');
    }
}
