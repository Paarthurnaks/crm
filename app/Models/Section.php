<?php

namespace App\Models;

use App\Enums\ProhibitedFieldEnum;
use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     title="Section",
 *     description="Раздел",
 *     @OA\Xml(
 *         name="Section"
 *     )
 * )
 */
class Section extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        "entity",
        "title",
        "user_id",
        "position",
        "deal_id",
        "sales_funnel_id",
        "is_template",
        "main_section",
        "type",
        "is_customized",
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
     * @var integer
     */

    private $position;
    /**
     * @OA\Property(
     *      title="fields",
     *      description="поля раздела",
     *      type="array",
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
        return $this->hasMany(SectionField::class)->orderBy("position");
    }

    public function availableFields(): HasMany
    {
        return $this->hasMany(SectionField::class)
            ->orderBy("position")
            ->where(function($query) {
                $query->whereIn("user_field_id", Auth::user()->role->userFields()->wherePivot("type", "view")->get()->pluck("id"))
                    ->orWhereIn("field_id", Auth::user()->role->fields()->wherePivot("type", "view")->get()->pluck("id"))
                    ->get();
            });
    }

    public function availableFieldsView(): HasMany
    {
        return $this->hasMany(SectionField::class)
            ->orderBy("position")
            ->where(function($query) {
                $query->whereIn("user_field_id", Auth::user()->role->userFields()->wherePivot("type", "view")->get()->pluck("id"))
                    ->orWhereIn("field_id", Auth::user()->role->fields()->wherePivot("type", "view")->get()->pluck("id"))
                    ->get();
            });
    }

    public function availableFieldsCreate(): HasMany
    {
        return $this->hasMany(SectionField::class)
            ->orderBy("position")
            ->whereDoesntHave('field', function ($query) {
                $query->whereIn('code', ProhibitedFieldEnum::values());
            })
            ->where(function($query) {
                $query->whereIn("user_field_id", Auth::user()->role->userFields()->wherePivot("type", "create")->get()->pluck("id"))
                    ->orWhereIn("field_id", Auth::user()->role->fields()->wherePivot("type", "create")->get()->pluck("id"))
                    ->get();
            });
    }

    public function availableFieldsUpdate(): HasMany
    {
        return $this->hasMany(SectionField::class)
            ->orderBy("position")
            ->whereDoesntHave('field', function ($query) {
                $query->whereIn('code', ProhibitedFieldEnum::values());
            })
            ->where(function($query) {
                $query->whereIn("user_field_id", Auth::user()->role->userFields()->wherePivot("type", "update")->get()->pluck("id"))
                    ->orWhereIn("field_id", Auth::user()->role->fields()->wherePivot("type", "update")->get()->pluck("id"))
                    ->get();
            });
    }
}
