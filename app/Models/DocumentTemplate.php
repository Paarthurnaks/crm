<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $title
 * @property int $creator_id
 * @property string $entity
 *
 * @OA\Schema(
 *     title="DocumentTemplate",
 *     description="Шаблон документов",
 *     @OA\Xml(
 *         name="DocumentTemplate"
 *     )
 * )
 */
class DocumentTemplate extends Model implements HasMedia
{
    use HasFactory, Filterable, InteractsWithMedia;

    protected $fillable = [
        'title',
        'creator_id',
        'entity',
        'created_at',
        'updated_at',
    ];

    /**
     * @OA\Property(
     *     title="id",
     *     description="id",
     *     format="int64",
     *     example=1
     * )
     *
     * @var int
     */
    private int $id;

    /**
     * @OA\Property(
     *      title="title",
     *      description="название шаблона",
     *      example="Генерация счета"
     * )
     *
     * @var string
     */
    private string $title;

    /**
     * @OA\Property(
     *     title="creator_id",
     *     description="id того кто загрузил шаблон",
     *     format="int64",
     *     example=12
     * )
     *
     * @var int
     */
    private int $creator_id;

    /**
     * @OA\Property(
     *      title="entity",
     *      description="сущность к которой принадлежит шаблон",
     *      example="deal"
     * )
     *
     * @var string
     */
    private string $entity;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "creator_id");
    }
}
