<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @property int $field_id
 * @property string $option
 * @property int $sort_order
 *
 * @OA\Schema(
 *     title="UserFieldEnumValue",
 *     description="Значение пользовательского поля",
 *
 *     @OA\Xml(
 *         name="UserFieldValue"
 *     )
 * )
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
