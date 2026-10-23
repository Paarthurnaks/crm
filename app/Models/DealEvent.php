<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     title="DealEvent",
 *     description="Событие сделки",
 *
 *     @OA\Xml(
 *         name="DealEvent"
 *     )
 * )
 */
class DealEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_id',
        'initiator_id',
        'type',
        'changed_field',
        'old_value',
        'new_value',
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
     *     title="initiator_id",
     *     description="id инициатора события",
     *     format="int64",
     *     example=4
     * )
     *
     * @var bigInteger
     */
    private $initiator_id;

    /**
     * @OA\Property(
     *      title="type",
     *      description="тип события (new_deal, change_field, change_stage, change_responsible)",
     *      example="change_field"
     * )
     *
     * @var string
     */
    private $type;

    /**
     * @OA\Property(
     *      title="changed_field",
     *      description="изменённое поле",
     *      example="Заголовок"
     * )
     *
     * @var string
     */
    private $changed_field;

    /**
     * @OA\Property(
     *      title="old_value",
     *      description="предыдущее начение",
     *      example="Сделка #50"
     * )
     *
     * @var string
     */
    private $old_value;

    /**
     * @OA\Property(
     *      title="new_value",
     *      description="новое значение",
     *      example="Сделка #51"
     * )
     *
     * @var string
     */
    private $new_value;

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }
}
