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
 * @property int $entity_id
 * @property int $field_id
 * @property string $value
 *
 * @OA\Schema(
 *     title="UserFieldValue",
 *     description="Значение пользовательского поля",
 *     @OA\Xml(
 *         name="UserFieldValue"
 *     )
 * )
 */
class UserFieldValue extends Model implements HasMedia
{
    use HasFactory, Filterable, InteractsWithMedia;

    protected $fillable = [
        "entity_id",
        "field_id",
        "value",
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
     *     title="entity_id",
     *     description="id привязанной сущности",
     *     format="int64",
     *     example=12
     * )
     *
     * @var int
     */
    private int $entity_id;

    /**
     * @OA\Property(
     *     title="field_id",
     *     description="id пользовательского поля",
     *     format="int64",
     *     example=2
     * )
     *
     * @var int
     */
    private int $field_id;

    /**
     * @OA\Property(
     *      title="value",
     *      description="значение",
     *      example="тест"
     * )
     *
     * @var string
     */
    private string $value;

    public function userField(): BelongsTo
    {
        return $this->belongsTo(UserField::class, 'field_id');
    }
}
