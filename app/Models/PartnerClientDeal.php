<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * @property int $id
 * @property int $deal_id
 * @property int $client_id
 *
 * @OA\Schema(
 *     title="PartnerClientDeal",
 *     description="Связь между сделкой партнера и его клиентом",
 *     @OA\Xml(
 *         name="Deal"
 *     )
 * )
 */
class PartnerClientDeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_id',
        'client_id',
    ];

    /**
     * @OA\Property(
     *      title="ID",
     *      description="ID",
     *      example="23"
     * )
     *
     * @var int $id
     */
    private int $id;

    /**
     * @OA\Property(
     *      title="ID сделки",
     *      description="ID сделки",
     *      example="2"
     * )
     *
     * @var int $deal_id
     */
    private int $deal_id;

    /**
     * @OA\Property(
     *      title="ID клиента",
     *      description="ID клиента",
     *      example="34"
     * )
     *
     * @var int $client_id
     */
    private int $client_id;

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }
}
