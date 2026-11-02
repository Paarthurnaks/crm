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
 * @OA\Schema(
 *     title="DocumentTemplate",
 *     description="Шаблон документов",
 *
 *     @OA\Xml(
 *         name="DocumentTemplate"
 *     )
 * )
 *
 * @property int $id
 * @property string $title
 * @property int $creator_id
 * @property string $entity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class DocumentTemplate extends Model implements HasMedia
{
    use Filterable, HasFactory, InteractsWithMedia;

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
     */
    private int $id;

    /**
     * @OA\Property(
     *      title="title",
     *      description="название шаблона",
     *      example="Генерация счета"
     * )
     */
    private string $title;

    /**
     * @OA\Property(
     *     title="creator_id",
     *     description="id того кто загрузил шаблон",
     *     format="int64",
     *     example=12
     * )
     */
    private int $creator_id;

    /**
     * @OA\Property(
     *      title="entity",
     *      description="сущность к которой принадлежит шаблон",
     *      example="deal"
     * )
     */
    private string $entity;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
