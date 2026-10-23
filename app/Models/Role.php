<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Annotations as OA;

/**
 * @property int $id
 * @OA\Schema(
 *     title="Role",
 *     description="Роль",
 *     @OA\Xml(
 *         name="Role"
 *     )
 * )
 */
class Role extends Model
{
    use HasFactory, Filterable;

    const COMMON = 1;

    protected $fillable = [
        "title",
        "original_title",
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
     *      title="title",
     *      description="название роли",
     *      example="Администратор"
     * )
     *
     * @var string
     */
    private $title;
    /**
     * @OA\Property(
     *      title="permissions",
     *      description="разрешения роли",
     *      type="array",
     *      @OA\Items(
     *          ref="#/components/schemas/Permission"
     *      )
     * )
     *
     * @var array
     */
    private $permissions;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function availableDiskEntities(): BelongsToMany
    {
        return $this->belongsToMany(DiskEntity::class, "role_disk_entities", "role_id", "disk_entity_id");
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, "role_fields", "role_id", "field_id");
    }

    public function userFields(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, "role_user_fields", "role_id", "user_field_id");
    }

    public function fieldsView(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, "role_fields", "role_id", "field_id")->wherePivot("type", "view");
    }

    public function userFieldsView(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, "role_user_fields", "role_id", "user_field_id")->wherePivot("type", "view");
    }

    public function fieldsCreate(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, "role_fields", "role_id", "field_id")->wherePivot("type", "create");
    }

    public function userFieldsCreate(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, "role_user_fields", "role_id", "user_field_id")->wherePivot("type", "create");
    }

    public function fieldsUpdate(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, "role_fields", "role_id", "field_id")->wherePivot("type", "update");
    }

    public function userFieldsUpdate(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, "role_user_fields", "role_id", "user_field_id")->wherePivot("type", "update");
    }
}
