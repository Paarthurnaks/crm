<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     title="Report",
 *     description="Отчёт",
 *
 *     @OA\Xml(
 *         name="Report"
 *     )
 * )
 */
class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'period_from',
        'period_to',
        'filter',
        'author_id',
    ];

    protected $casts = [
        'filter' => 'array',
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
     *      description="название отчёта",
     *      example="Общее"
     * )
     *
     * @var string
     */
    private $title;

    /**
     * @OA\Property(
     *      title="title",
     *      description="описание отчёта",
     *      example="Описание"
     * )
     *
     * @var string
     */
    private $description;

    /**
     * @OA\Property(
     *     title="period_from",
     *     description="Период от",
     *     format="datetime",
     *     example="2022-12-27 10:09:20"
     * )
     *
     * @var datetime
     */
    private $period_from;

    /**
     * @OA\Property(
     *     title="period_to",
     *     description="Период до",
     *     format="datetime",
     *     example="2022-12-27 10:09:20"
     * )
     *
     * @var datetime
     */
    private $period_to;

    /**
     * @OA\Property(
     *      type="array",
     *      title="filter",
     *      description="фильтр отчёта",
     *      example="",
     *
     *      @OA\Items(),
     * )
     *
     * @var mixed[]
     */
    private $filter;

    /**
     * @OA\Property(
     *     title="author_id",
     *     description="id автора",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bigInteger
     */
    private $author_id;

    public function fields(): HasMany
    {
        return $this->hasMany(ReportField::class)->orderBy('position');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function userAccess(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'report_accesses');
    }

    public function canBeViewedBy(int $userId): bool
    {
        return (int) $this->getAttribute('author_id') === $userId
            || ! $this->userAccess()->exists()
            || $this->userAccess()->whereKey($userId)->exists();
    }
}
