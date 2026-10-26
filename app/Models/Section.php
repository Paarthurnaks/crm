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
