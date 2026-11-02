<?php

namespace App\Models;

use App\Enums\TaskStatusEnum;
use App\Http\Filters\Traits\Filterable;
use BeyondCode\Comments\Traits\HasComments;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OpenApi\Annotations as OA;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @OA\Schema(
 *     title="Task",
 *     description="Задача",
 *
 *     @OA\Xml(
 *         name="Task"
 *     )
 * )
 *
 * @property int $id
 * @property int|null $deal_id
 * @property int $creator_id
 * @property string $title
 * @property string $status
 * @property string|null $deadline
 * @property string|null $start_time
 * @property string $type
 * @property string|null $email
 * @property string|null $client_full_name
 * @property string|null $service
 * @property int|null $docket_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Deal|null $deal
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $executors
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $observers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskEvent> $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EntityActivity> $activities
 * @property-read string $description
 * @property-write string|null $description
 * @method static \Illuminate\Database\Eloquent\Builder<static> ownTasks(?int $actorId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static> openedTasks()
 * @method static \Illuminate\Database\Eloquent\Builder<static> allTasks()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Task extends Model implements HasMedia
{
    use Filterable,
        HasComments,
        HasFactory,
        InteractsWithMedia;

    protected $fillable = [
        'deal_id',
        'creator_id',
        'title',
        'description',
        'status',
        'deadline',
        'start_time',
        'type',
        'email',
        'client_full_name',
        'service',
        'docket_number',
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
     *      title="creator_id",
     *      description="постановщик задачи",
     *      type="int",
     *      example=150
     * )
     */
    private float $creator_id;

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
     *     title="expired",
     *     description="задача просрочена",
     *     type="bool",
     *     example="false"
     * )
     */
    private bool $expired;

    /**
     * @OA\Property(
     *     title="deadline",
     *     description="крайний срок",
     *     type="string",
     *     example="2024-02-01 15:17"
     * )
     */
    private string $deadline;

    /**
     * @OA\Property(
     *     title="start_time",
     *     description="крайний срок",
     *     type="string",
     *     example="2024-02-01 15:17"
     * )
     */
    private string $start_time;

    /**
     * @OA\Property(
     *     title="status",
     *     description="статус задачи",
     *     type="string",
     *     example="new"
     * )
     */
    private string $status;

    /**
     * @OA\Property(
     *     title="title",
     *     description="название задачи",
     *     type="string",
     *     example="242_test"
     * )
     */
    private string $title;

    /**
     * @OA\Property(
     *     title="description",
     *     description="описание задачи",
     *     type="string",
     *     example="242_test description"
     * )
     */
    private string $description;

    /**
     * @OA\Property(
     *     title="executors",
     *     description="ответственные",
     *     type="array",
     *
     *     @OA\Items(
     *          ref="#/components/schemas/User"
     *     )
     * )
     */
    private array $executors;

    /**
     * @OA\Property(
     *     title="$observers",
     *     description="наблюдатели",
     *     type="array",
     *
     *     @OA\Items(
     *          ref="#/components/schemas/User"
     *     )
     * )
     */
    private array $observers;

    /**
     * @OA\Property(
     *     title="type",
     *     description="Тип задачи",
     *     type="string",
     *     example="helpdesk"
     * )
     */
    private string $type;

    public function getDescriptionAttribute($value): string
    {
        return strip_tags($value);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    public function executors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_executors', 'task_id', 'user_id');
    }

    public function observers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_observers', 'task_id', 'user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TaskEvent::class, 'task_id', 'id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(EntityActivity::class, 'activityable');
    }

    public function scopeOwnTasks($query, ?int $actorId = null): Builder
    {
        $actorId ??= auth()->id();

        return $query->where(function ($query) use ($actorId) {
            $query->where('creator_id', $actorId)
                ->orWhereHas('executors', function ($query) use ($actorId) {
                    $query->where('user_id', $actorId);
                })
                ->orWhereHas('observers', function ($query) use ($actorId) {
                    $query->where('user_id', $actorId);
                });
        });
    }

    public function scopeOpenedTasks($query): Builder
    {
        return $query->where('status', '!=', TaskStatusEnum::CLOSED);
    }

    public function scopeAllTasks($query): Builder
    {
        return $query;
    }
}
