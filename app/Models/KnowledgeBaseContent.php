<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @OA\Schema(
 *     title="KnowledgeBaseContent",
 *     description="Контент базы знаний",
 *
 *     @OA\Xml(
 *         name="KnowledgeBaseContent"
 *     )
 * )
 *
 * @property int $id
 * @property int $knowledge_base_id
 * @property string|null $content
 * @property string $type
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\KnowledgeBase|null $knowledgeBase
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class KnowledgeBaseContent extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'knowledge_base_id',
        'content',
        'type',
        'order',
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
     *     title="content",
     *     format="string",
     *     example="ccontent of base"
     * )
     */
    private string $content;

    /**
     * @OA\Property(
     *     title="type",
     *     format="string",
     *     example="pdf"
     * )
     */
    private string $type;

    /**
     * @OA\Property(
     *     title="order",
     *     format="integer",
     *     example="2"
     * )
     */
    private int $order;

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }
}
