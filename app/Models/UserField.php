<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Annotations as OA;

/**
 * @property string $name
 * @property string $entity
 * @property string $type
 * @property string $code
 *
 * @OA\Schema(
 *     title="UserField",
 *     description="Пользовательское поле",
 *
 *     @OA\Xml(
 *         name="UserField"
 *     )
 * )
 */
class UserField extends Model
{
    use Filterable,HasFactory;

    protected $fillable = [
        'name',
        'entity',
        'type',
        'code',
        'is_required',
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
     *      title="name",
     *      description="название поля",
     *      example="Тестовое поле"
     * )
     */
    private string $name;

    /**
     * @OA\Property(
     *     title="entity",
     *     description="сущность",
     *     example="deal"
     * )
     */
    private string $entity;

    /**
     * @OA\Property(
     *     title="code",
     *     description="код поля",
     *     example="uf_deal_1"
     * )
     */
    private string $code;

    /**
     * @OA\Property(
     *     title="type",
     *     description="тип поля (integer, float, boolean, datetime, date, enum, file, user, deal)",
     *     example="integer"
     * )
     */
    private string $type;

    public function userFieldEnumValues(): HasMany
    {
        return $this->hasMany(UserFieldEnumValue::class, 'field_id', 'id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(UserFieldValue::class, 'field_id');
    }
}
