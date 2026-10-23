<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @property int $id
 * @property int $user_id
 * @property int $task_id
 *
 * @OA\Schema(
 *     title="TaskExecutor",
 *     description="Ответственный задачи",
 *
 *     @OA\Xml(
 *         name="TaskExecutor"
 *     )
 * )
 */
class TaskExecutor extends Model
{
    use Filterable, HasFactory;

    /**
     * @var string[]
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'created_at',
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
     *      description="ответственный задачи",
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
