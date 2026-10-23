<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $knowledge_base_id
 * @property string $content
 * @property string $type
 * @property string $order
 *
 * @OA\Schema(
 *     title="KnowledgeBaseContent",
 *     description="Контент базы знаний",
 *     @OA\Xml(
 *         name="KnowledgeBaseContent"
 *     )
 * )
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
     *
     * @var int $id
     */
    private int $id;

    /**
     * @OA\Property(
     *     title="content",
     *     format="string",
     *     example="ccontent of base"
     * )
     *
     * @var string $content
     */
    private string $content;

    /**
     * @OA\Property(
     *     title="type",
     *     format="string",
     *     example="pdf"
     * )
     *
     * @var string $type
     */
    private string $type;

    /**
     * @OA\Property(
     *     title="order",
     *     format="integer",
     *     example="2"
     * )
     *
     * @var int $content
     */
    private int $order;

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }
}
