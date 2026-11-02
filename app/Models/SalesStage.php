<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     title="SalesStage",
 *     description="Стадия продажи",
 *
 *     @OA\Xml(
 *         name="SalesStage"
 *     )
 * )
 *
 * @property int $id
 * @property string $title
 * @property string $color
 * @property int $position
 * @property string $type
 * @property int $sales_funnel_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SalesFunnel|null $salesFunnel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Deal> $deals
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SalesTunnel> $salesTunnels
 * @property array<string, mixed>|null $metrics
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class SalesStage extends Model
{
    use Filterable, HasFactory;

    const COMMON = 1;

    protected $fillable = [
        'title',
        'color',
        'position',
        'type',
        'sales_funnel_id',
    ];

    protected $appends = [
        'metrics',
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
     *      description="название стадии",
     *      example="В работе"
     * )
     *
     * @var string
     */
    private $title;

    /**
     * @OA\Property(
     *      title="color",
     *      description="цвет стадии",
     *      example="#ffffff"
     * )
     *
     * @var string
     */
    private $color;

    /**
     * @OA\Property(
     *      title="positon",
     *      description="позиция стадии (для сортировки)",
     *      example=1
     * )
     *
     * @var int
     */
    private $positon;

    /**
     * @OA\Property(
     *      title="type",
     *      description="тип стадии (default, success, unsuccess)",
     *      example="success"
     * )
     *
     * @var string
     */
    private $type;

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
     *      title="metrics",
     *      description="метрики",
     *      type="array",
     *
     *      @OA\Items(
     *
     *          @OA\Property(property="count", format="int64", example=10),
     *          @OA\Property(property="totalAmount", format="float", example=189.3)
     *      )
     * )
     */
    private $metrics;

    protected function getMetricsAttribute()
    {
        return $this->metrics;
    }

    protected function setMetricsAttribute(array $value)
    {
        $this->metrics = $value;
    }

    public function salesFunnel(): BelongsTo
    {
        return $this->belongsTo(SalesFunnel::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function salesTunnels(): HasMany
    {
        return $this->hasMany(SalesTunnel::class, 'from_stage_id', 'id');
    }
}
