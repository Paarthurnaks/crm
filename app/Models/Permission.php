<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @OA\Schema(
 *     title="Permission",
 *     description="Объект разрешений",
 *
 *     @OA\Xml(
 *         name="Permission"
 *     )
 * )
 *
 * @property int $id
 * @property int $role_id
 * @property string $entity
 * @property string $view
 * @property string $create
 * @property string $update
 * @property string $delete
 * @property string|null $import
 * @property string|null $export
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Role|null $role
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 *
 * @mixin Builder<static>
 */
class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'entity',
        'view',
        'create',
        'update',
        'delete',
        'import',
        'export',
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
    private $id;

    /**
     * @OA\Property(
     *      title="entity",
     *      description="название сущности разрешений (users, deals, tasks)",
     *      example="deals"
     * )
     *
     * @var string
     */
    private $entity;

    /**
     * @OA\Property(
     *     title="view",
     *     description="разрешение на просмотр (all, opened, own, forbidden)",
     *     format="string",
     *     example="all"
     * )
     *
     * @var string
     */
    private $view;

    /**
     * @OA\Property(
     *     title="create",
     *     description="разрешение на создание (all, opened, own, forbidden)",
     *     format="string",
     *     example="opened"
     * )
     *
     * @var string
     */
    private $create;

    /**
     * @OA\Property(
     *     title="update",
     *     description="разрешение на редактирование (all, opened, own, forbidden)",
     *     format="string",
     *     example="own"
     * )
     *
     * @var string
     */
    private $update;

    /**
     * @OA\Property(
     *     title="delete",
     *     description="разрешение на удаление (all, opened, own, forbidden)",
     *     format="string",
     *     example="forbidden"
     * )
     *
     * @var string
     */
    private $delete;

    /**
     * @OA\Property(
     *     title="delete",
     *     description="разрешение на удаление (all, opened, own, forbidden)",
     *     format="string",
     *     example="all"
     * )
     *
     * @var string
     */
    private $import;

    /**
     * @OA\Property(
     *     title="delete",
     *     description="разрешение на удаление (all, opened, own, forbidden)",
     *     format="string",
     *     example="all"
     * )
     *
     * @var string
     */
    private $export;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
