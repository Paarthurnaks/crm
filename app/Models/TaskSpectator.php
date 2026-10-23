<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $task_id
 *
 * @OA\Schema(
 *     title="TaskSpectator",
 *     description="Наблюдатель задачи",
 *
 *     @OA\Xml(
 *         name="TaskSpectator"
 *     )
 * )
 */
class TaskSpectator extends Model
{
    use HasFactory;

    protected $table = 'task_observers';

    protected $fillable = [
        'user_id',
        'task_id',
    ];

    /**
     * @OA\Property(
     *     title="ID",
     *     description="ID",
     *     type="integer",
     *     example=15
     * )
     */
    private int $id;

    /**
     * @OA\Property(
     *      title="user_id",
     *      description="id наблюдателя",
     *      type="integer",
     *      example=150
     * )
     */
    private int $user_id;

    /**
     * @OA\Property(
     *     title="task_id",
     *     description="id привязанной задачи",
     *     type="integer",
     *     example=1
     * )
     */
    private int $task_id;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
