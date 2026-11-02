<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     title="UserFieldEnumValue",
 *     description="Значение пользовательского поля",
 *
 *     @OA\Xml(
 *         name="UserFieldValue"
 *     )
 * )
 *
 * @property int $id
 * @property string $option
 * @property int $field_id
 * @property int|null $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class UserFieldEnumValue extends Model
{
    use Filterable,HasFactory;

    protected $fillable = [
        'option',
        'field_id',
        'sort_order',
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
     *     title="option",
     *     description="значение списка енама",
     *     example="зеленый"
     * )
     */
    private string $option;

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
     *      title="sort_order",
     *      description="сортировка",
     *      example=1
     * )
     */
    private int $sort_order;
}
