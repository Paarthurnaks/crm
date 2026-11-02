<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     title="SalesFunnel",
 *     description="Направление продажи",
 *
 *     @OA\Xml(
 *         name="SalesFunnel"
 *     )
 * )
 *
 * @property int $id
 * @property int $from_stage_id
 * @property int $to_stage_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SalesStage|null $toStage
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class SalesTunnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_stage_id',
        'to_stage_id',
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
     *     title="from_stage_id",
     *     description="id точки входа",
     *     format="int64",
     *     example=3
     * )
     *
     * @var bigInteger
     */
    private $from_stage_id;

    /**
     * @OA\Property(
     *     title="to_stage_id",
     *     description="id точки выхода",
     *     format="int64",
     *     example=8
     * )
     *
     * @var bigInteger
     */
    private $to_stage_id;

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(SalesStage::class);
    }
}
