<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @property int $id
 * @property int $deal_id
 * @property int $author_id
 * @property float $sum
 * @property string $invoice_date
 *
 * @OA\Schema(
 *     title="Account",
 *     description="Счет",
 *
 *     @OA\Xml(
 *         name="Account"
 *     )
 * )
 */
class Account extends Model
{
    use Filterable, HasFactory;

    protected $fillable = [
        'deal_id',
        'sum',
        'invoice_date',
        'author_id',
    ];

    /**
     * @OA\Property(
     *     title="ID",
     *     description="ID",
     *     type="int",
     *     example=15
     * )
     */
    private int $id;

    /**
     * @OA\Property(
     *      title="sum",
     *      description="сумма счета",
     *      type="double",
     *      example=15000
     * )
     */
    private float $sum;

    /**
     * @OA\Property(
     *     title="deal_id",
     *     description="id привязанной сделки",
     *     type="int",
     *     example=1
     * )
     */
    private int $deal_id;

    /**
     * @OA\Property(
     *     title="invoice_date",
     *     description="дата выставления счета",
     *     type="string",
     *     example="2024-02-01"
     * )
     */
    private string $invoice_date;

    /**
     * @OA\Property(
     *     title="author_id",
     *     description="id того кто выставил счет",
     *     type="int",
     *     example=12
     * )
     */
    private int $author_id;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }
}
