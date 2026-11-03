<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     title="TaskEvent",
 *     description="Событие задачи",
 *
 *     @OA\Xml(
 *         name="TaskEvent"
 *     )
 * )
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $initiator_id
 * @property string $type
 * @property string|null $changed_field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $initiator
 * @property-read Task|null $task
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 *
 * @mixin Builder<static>
 */
class TaskEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
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
     * @var int
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
     * @var int
     */
    private $initiator_id;

    /**
     * @OA\Property(
     *      title="type",
     *      description="тип события (new_task, change_field, change_stage)",
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
     *      example="Задача #50"
     * )
     *
     * @var string
     */
    private $old_value;

    /**
     * @OA\Property(
     *      title="new_value",
     *      description="новое значение",
     *      example="Задача #51"
     * )
     *
     * @var string
     */
    private $new_value;

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
