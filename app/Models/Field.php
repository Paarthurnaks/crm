<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     title="Field",
 *     description="Поле",
 *
 *     @OA\Xml(
 *         name="Field"
 *     )
 * )
 *
 * @property int $id
 * @property string $name
 * @property string $entity
 * @property string $type
 * @property string $code
 * @property bool $is_required
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Field extends Model
{
    use HasFactory;

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
     *     description="тип поля (integer, string, float, boolean, datetime, date, enum, file, user, deal, funnel, stage)",
     *     example="integer"
     * )
     */
    private string $type;
}
