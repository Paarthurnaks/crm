<?php

namespace App\Models;

use App\Enums\SalesStageEnum;
use App\Http\Filters\Traits\Filterable;
use App\Http\Resources\FieldResource;
use App\Http\Resources\UserFieldResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @OA\Schema(
 *     title="SalesFunnel",
 *     description="Направление продажи",
 *     @OA\Xml(
 *         name="SalesFunnel"
 *     )
 * )
 */
class SalesFunnel extends Model
{
    use HasFactory, Filterable;

    const COMMON = 1;

    protected $fillable = [
        "title",
        "position"
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
     *      description="название воронки",
     *      example="Новая воронка"
     * )
     *
     * @var string
     */
    private $title;
    /**
     * @OA\Property(
     *      title="stages",
     *      description="стадии воронки",
     *      type="array",
     *      @OA\Items(
     *          ref="#/components/schemas/SalesStage"
     *      )
     * )
     *
     * @var array
     */
    private $stages;

    public function salesStages(): HasMany
    {
        return $this->hasMany(SalesStage::class);
    }

    public function sortedSalesStages()
    {

        $sortedByPosition = $this->salesStages->sortBy("position");

        $sortedByType = $sortedByPosition->sortBy(function ($stage) {
            switch ($stage->type) {
                case SalesStageEnum::DEFAULT->value:
                    return 1;
                    break;
                case SalesStageEnum::SUCCESS->value:
                    return 2;
                    break;
                case SalesStageEnum::UNSUCCESS->value:
                    return 3;
                    break;
            }
        });

        return $sortedByType;
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'sales_funnel_fields', 'sales_funnel_id', 'field_id');
    }

    public function userFields(): BelongsToMany
    {
        return $this->belongsToMany(UserField::class, 'sales_funnel_fields', 'sales_funnel_id', 'user_field_id');
    }


}
