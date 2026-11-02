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
 *     title="UserFieldValue",
 *     description="Значение пользовательского поля",
 *
 *     @OA\Xml(
 *         name="UserFieldValue"
 *     )
 * )
 *
 * @property int $id
 * @property int $entity_id
 * @property int $field_id
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\UserField|null $userField
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class UserFieldValue extends Model implements HasMedia
{
    use Filterable, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'entity_id',
        'field_id',
        'value',
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
     *     title="entity_id",
     *     description="id привязанной сущности",
     *     format="int64",
     *     example=12
     * )
     */
    private int $entity_id;

    /**
     * @OA\Property(
     *     title="field_id",
     *     description="id пользовательского поля",
     *     format="int64",
     *     example=2
     * )
     */
    private int $field_id;

    /**
     * @OA\Property(
     *      title="value",
     *      description="значение",
     *      example="тест"
     * )
     */
    private string $value;

    public function userField(): BelongsTo
    {
        return $this->belongsTo(UserField::class, 'field_id');
    }
}
