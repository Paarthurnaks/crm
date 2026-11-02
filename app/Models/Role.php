<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     title="Role",
 *     description="Роль",
 *
 *     @OA\Xml(
 *         name="Role"
 *     )
 * )
 *
 * @property int $id
 * @property string $title
 * @property string|null $original_title
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DiskEntity> $availableDiskEntities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Field> $fields
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserField> $userFields
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Field> $fieldsView
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserField> $userFieldsView
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Field> $fieldsCreate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserField> $userFieldsCreate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Field> $fieldsUpdate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserField> $userFieldsUpdate
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Role extends Model
{
    use Filterable, HasFactory;

    const COMMON = 1;

    protected $fillable = [
        'title',
        'original_title',
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
     *
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
        return $this->belongsToMany(DiskEntity::class, 'role_disk_entities', 'role_id', 'disk_entity_id');
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'role_fields', 'role_id', 'field_id');
    }

    public function userFields(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, 'role_user_fields', 'role_id', 'user_field_id');
    }

    public function fieldsView(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'role_fields', 'role_id', 'field_id')->wherePivot('type', 'view');
    }

    public function userFieldsView(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, 'role_user_fields', 'role_id', 'user_field_id')->wherePivot('type', 'view');
    }

    public function fieldsCreate(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'role_fields', 'role_id', 'field_id')->wherePivot('type', 'create');
    }

    public function userFieldsCreate(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, 'role_user_fields', 'role_id', 'user_field_id')->wherePivot('type', 'create');
    }

    public function fieldsUpdate(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'role_fields', 'role_id', 'field_id')->wherePivot('type', 'update');
    }

    public function userFieldsUpdate(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, 'role_user_fields', 'role_id', 'user_field_id')->wherePivot('type', 'update');
    }
}
