<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use App\Traits\HasUserFields;
use BeyondCode\Comments\Traits\HasComments;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use OpenApi\Annotations as OA;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @OA\Schema(
 *     title="Deal",
 *     description="Сделка",
 *
 *     @OA\Xml(
 *         name="Deal"
 *     )
 * )
 *
 * @property int $id
 * @property string $title
 * @property int $sales_stage_id
 * @property int $sales_funnel_id
 * @property int $creator_id
 * @property int|null $responsible_id
 * @property string $note
 * @property string|float $amount
 * @property int|null $creditor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SalesStage|null $salesStage
 * @property-read SalesFunnel|null $salesFunnel
 * @property-read User|null $creator
 * @property-read User|null $responsible
 * @property-read Collection<int, DealEvent> $events
 * @property-read Collection<int, EntityActivity> $activities
 * @property-read User|string|null $client
 * @property-write int|string|null $client
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> allDeals()
 * @method static \Illuminate\Database\Eloquent\Builder<static> ownDeals(int $actorId)
 * @method static \Illuminate\Database\Eloquent\Builder<static> openedDeals()
 *
 * @property-read Collection<int, Media> $media
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, UserFieldValue> $userFieldValues
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Deal extends Model implements HasMedia
{
    use Filterable,
        HasComments,
        HasFactory,
        HasUserFields,
        InteractsWithMedia;

    protected $fillable = [
        'title',
        'sales_stage_id',
        'sales_funnel_id',
        'creator_id',
        'responsible_id',
        'note',
        'amount',
        'client',
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
     *      title="title",
     *      description="название сделки",
     *      example="Сделка #50"
     * )
     *
     * @var string
     */
    private $title;

    /**
     * @OA\Property(
     *     title="sales_stage",
     *     ref="#/components/schemas/SalesStage"
     * )
     *
     * @var SalesStage
     */
    private $sales_stage;

    /**
     * @OA\Property(
     *     title="sales_funnel",
     *     ref="#/components/schemas/SalesFunnel"
     * )
     *
     * @var SalesFunnel
     */
    private $sales_funnel;

    /**
     * @OA\Property(
     *     title="creator",
     *     ref="#/components/schemas/User"
     * )
     *
     * @var User
     */
    private $creator;

    /**
     * @OA\Property(
     *     title="responsible_id",
     *     ref="#/components/schemas/User"
     * )
     *
     * @var User
     */
    private $responsible;

    /**
     * @OA\Property(
     *      title="note",
     *      description="заметка",
     *      example="текст"
     * )
     *
     * @var string
     */
    private $note;

    /**
     * @OA\Property(
     *      title="amount",
     *      description="сумма сделки",
     *      example="1.50"
     * )
     *
     * @var float
     */
    private $amount;

    /**
     * @OA\Property(
     *      title="client",
     *      description="ID клиента или строка",
     *      example="2"
     * )
     *
     * @var string
     */
    private $client;

    /**
     * @OA\Property(
     *      title="user_field_values",
     *      ref="#/components/schemas/UserFieldValue"
     * )
     *
     * @var UserFieldValue
     */
    private $user_field_values;

    public function getNoteAttribute($value): string
    {
        return strip_tags($value);
    }

    public function salesStage(): BelongsTo
    {
        return $this->belongsTo(SalesStage::class);
    }

    public function salesFunnel(): BelongsTo
    {
        return $this->belongsTo(SalesFunnel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DealEvent::class, 'deal_id', 'id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(EntityActivity::class, 'entity');
    }

    public function scopeAllDeals($query): Builder
    {
        return $query;
    }

    public function scopeOwnDeals($query, int $actorId): Builder
    {
        return $query->where(function ($query) use ($actorId) {
            $query->where('creator_id', $actorId)
                ->orWhere('responsible_id', $actorId);
        });
    }

    public function scopeOpenedDeals($query): Builder
    {
        return $query->whereHas('salesStage', function ($query) {
            $query->where('type', 'default');
        });
    }

    public function setClientAttribute($value): void
    {
        if (is_int($value)) {
            $this->attributes['client'] = $value;
        } elseif (is_null($value) || empty($value)) {
            $this->attributes['client'] = null;
        } else {
            $this->attributes['client'] = 'str_'.$value;
        }
    }

    public function getClientAttribute($value): User|string|null
    {
        if ($value === null) {
            return null;
        }

        if (ctype_digit((string) $value)) {
            if ($this->relationLoaded('resolvedClient')) {
                return $this->getRelation('resolvedClient');
            }

            return User::find((int) $value);
        }

        return str_starts_with($value, 'str_') ? substr($value, 4) : $value;
    }
}
