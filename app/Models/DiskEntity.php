<?php

namespace App\Models;

use App\Enums\DiskEntityTypeEnum;
use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $path
 *
 * @OA\Schema(
 *     title="DiskEntity",
 *     description="Элемент диска (папка/файл)",
 *     @OA\Xml(
 *         name="DiskEntity"
 *     )
 * )
 */
class DiskEntity extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        "title",
        "who_created_id",
        "who_changed_id",
        "size",
        "type",
        "deleted",
        "path",
        "original_path",
        "mime_type",
    ];

    protected $casts = [
        'type' => DiskEntityTypeEnum::class,
    ];

    /**
     * @OA\Property(
     *     title="ID",
     *     description="ID",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bigInteger
     */
    private $id;

    /**
     * @OA\Property(
     *     title="title",
     *     description="title",
     *     format="string",
     *     example="Новая папка"
     * )
     *
     * @var string
     */

    private $title;

    /**
     * @OA\Property(
     *     title="who_created_id",
     *     description="кем создан",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bigInteger
     */
    private $who_created_id;

    /**
     * @OA\Property(
     *     title="who_changed_id",
     *     description="кем изменён",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bigInteger
     */
    private $who_changed_id;

    /**
     * @OA\Property(
     *     title="size",
     *     description="размер",
     *     format="int64",
     *     example=4096
     * )
     *
     * @var bigInteger
     */
    private $size;

    /**
     * @OA\Property(
     *     title="type",
     *     description="тип сущности",
     *     format="string",
     *     example="Папка"
     * )
     *
     * @var string
     */
    private $type;

    /**
     * @OA\Property(
     *     title="path",
     *     description="путь",
     *     format="string",
     *     example="/Папка/"
     * )
     *
     * @var string
     */

    private $path;

    /**
     * @OA\Property(
     *     title="original_path",
     *     description="служит для сохранения основного пути после перемещения в корзину",
     *     format="string",
     *     example="/Папка/"
     * )
     *
     * @var string
     */

    private $original_path;

    /**
     * @OA\Property(
     *     title="deleted",
     *     description="deleted",
     *     format="boolean",
     *     example=false
     * )
     *
     * @var boolean
     */
    private $deleted;

    /**
     * @OA\Property(
     *     title="mime_type",
     *     description="мим тип",
     *     format="string",
     *     example="png"
     * )
     *
     * @var string
     */
    private $mime_type;

    public function whoCreated(): BelongsTo
    {
        return $this->belongsTo(User::class, "who_created_id");
    }

    public function whoChanged(): BelongsTo
    {
        return $this->belongsTo(User::class, "who_changed_id");
    }

}
