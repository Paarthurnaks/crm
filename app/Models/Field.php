<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     title="Field",
 *     description="Поле",
 *     @OA\Xml(
 *         name="Field"
 *     )
 * )
 */
class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "entity",
        "type",
        "code",
        "is_required"
    ];

    /**
     * @OA\Property(
     *     title="ID",
     *     description="ID",
     *     format="int64",
     *     example=1
     * )
     *
     * @var int
     */
    private int $id;
    /**
     * @OA\Property(
     *      title="name",
     *      description="название поля",
     *      example="Тестовое поле"
     * )
     *
     * @var string
     */
    private string $name;
    /**
     * @OA\Property(
     *     title="entity",
     *     description="сущность",
     *     example="deal"
     * )
     *
     * @var string
     */
    private string $entity;

    /**
     * @OA\Property(
     *     title="code",
     *     description="код поля",
     *     example="uf_deal_1"
     * )
     *
     * @var string
     */
    private string $code;

    /**
     * @OA\Property(
     *     title="type",
     *     description="тип поля (integer, string, float, boolean, datetime, date, enum, file, user, deal, funnel, stage)",
     *     example="integer"
     * )
     *
     * @var string
     */
    private string $type;

}
