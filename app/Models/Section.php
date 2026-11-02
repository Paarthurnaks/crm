<?php

namespace App\Models;

use App\Enums\ProhibitedFieldEnum;
use App\Http\Filters\Traits\Filterable;
use App\Services\FieldAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     title="Section",
 *     description="Раздел",
 *
 *     @OA\Xml(
 *         name="Section"
 *     )
 * )
 *
 * @property int $id
 * @property string $entity
 * @property string $title
 * @property int $user_id
 * @property int $position
 * @property int|null $deal_id
 * @property int|null $sales_funnel_id
 * @property bool $is_template
 * @property bool $main_section
 * @property string|null $type
 * @property bool $is_customized
 * @property int|null $creditor_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Deal|null $deal
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SectionField> $fields
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SectionField> $availableFields
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SectionField> $availableFieldsView
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SectionField> $availableFieldsCreate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SectionField> $availableFieldsUpdate
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Section extends Model
{
    use Filterable, HasFactory;

    protected $fillable = [
        'entity',
        'title',
        'user_id',
        'position',
        'deal_id',
        'sales_funnel_id',
        'is_template',
        'main_section',
        'type',
        'is_customized',
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
     *      title="entity",
     *      description="название сущности",
     *      example="deal"
     * )
     *
     * @var string
     */
    private $entity;

    /**
     * @OA\Property(
     *      title="title",
     *      description="название раздела",
     *      example="Общее"
     * )
     *
     * @var string
     */
    private $title;

    /**
     * @OA\Property(
     *     title="user_id",
     *     description="id пользователя",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bigInteger
     */
    private $user_id;

    /**
     * @OA\Property(
     *     title="sales_funnel_id",
     *     description="id направления продажи",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bigInteger
     */
    private $sales_funnel_id;

    /**
     * @OA\Property(
     *     title="is_customized",
     *     description="кастомизированы ли поля у юзера",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bool
     */
    private $is_customized;

    /**
     * @OA\Property(
     *      title="position",
     *      description="позиция раздела (для сортировки)",
     *      example=1
     * )
     *
     * @var int
     */
    private $position;

    /**
     * @OA\Property(
     *      title="fields",
     *      description="поля раздела",
     *      type="array",
     *
     *      @OA\Items(
     *          ref="#/components/schemas/SectionField"
     *      )
     * )
     *
     * @var array
     */
    private $fields;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(SectionField::class)->orderBy('position');
    }

    public function availableFields(): HasMany
    {
        return $this->availableFieldsFor('view');
    }

    public function availableFieldsView(): HasMany
    {
        return $this->availableFieldsFor('view');
    }

    public function availableFieldsCreate(): HasMany
    {
        return $this->availableFieldsFor('create');
    }

    public function availableFieldsUpdate(): HasMany
    {
        return $this->availableFieldsFor('update');
    }

    private function availableFieldsFor(string $action): HasMany
    {
        $relation = $this->hasMany(SectionField::class)->orderBy('position');
        $user = Auth::user();

        if (! FieldAccess::supports($action) || ! $user?->role) {
            return $relation->whereRaw('1 = 0');
        }

        if ($action !== 'view') {
            $relation->whereDoesntHave('field', function ($query) {
                $query->whereIn('code', ProhibitedFieldEnum::values());
            });
        }

        if (FieldAccess::isSuperAdmin($user)) {
            return $relation;
        }

        $allowed = FieldAccess::allowedIds($user, $action);

        return $relation->where(function ($query) use ($allowed) {
            $query->whereIn('user_field_id', $allowed['user_fields'])
                ->orWhereIn('field_id', $allowed['fields']);
        });
    }
}
