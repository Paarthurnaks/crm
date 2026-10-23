<?php

namespace App\Models;

use App\Enums\DefaultRolesEnum;
use App\Http\Filters\Traits\Filterable;
use App\Traits\HasUserFields;
use BeyondCode\Comments\Traits\HasComments;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OpenApi\Annotations as OA;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $title
 * @property int $sales_stage_id
 * @property int $sales_funnel_id
 * @property int $creator_id
 * @property int $responsible_id
 * @property string $note
 * @property float $amount
 * @property string $client
 *
 * @OA\Schema(
 *     title="Deal",
 *     description="Сделка",
 *     @OA\Xml(
 *         name="Deal"
 *     )
 * )
 */
class Deal extends Model implements HasMedia
{
    use HasFactory,
        Filterable,
        HasComments,
        HasUserFields,
        InteractsWithMedia;

    protected $fillable = [
        "title",
        "sales_stage_id",
        "sales_funnel_id",
        "creator_id",
        "responsible_id",
        "note",
        "amount",
        "client"
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
        return $this->belongsTo(User::class, "creator_id");
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, "responsible_id");
    }

    public function events(): HasMany
    {
        return $this->hasMany(DealEvent::class, "deal_id", "id");
    }

    /**
     * @return MorphMany
     */
    public function activities(): MorphMany
    {
        $user = auth()->user();

        if($user->hasRole(DefaultRolesEnum::CLIENT->value)) {
            return $this->morphMany(EntityActivity::class, 'entity')
                ->where('activityable_type', \BeyondCode\Comments\Comment::class)
                ->whereHasMorph('activityable', [\BeyondCode\Comments\Comment::class], function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere('user_id', $user->partner?->id);
                });
        }

        return $this->morphMany(EntityActivity::class, 'entity');

    }

    /**
     * @param $query
     * @return Builder
     */
    public function scopeAllDeals($query): Builder
    {
        return $query;
    }

    /**
     * @param $query
     * @return Builder
     */
    public function scopeOwnDeals($query): Builder
    {
        return $query->where(function($query) {
            $query->where('creator_id', auth()->id())
                ->orWhere('responsible_id', auth()->id());
        });
    }

    /**
     * @param $query
     * @return Builder
     */
    public function scopeOpenedDeals($query): Builder
    {
        return $query->whereHas('salesStage', function ($query) {
            $query->where('type', 'default');
        });
    }

    public function setClientAttribute($value): void
    {
        if (is_integer($value)) {
            $this->attributes['client'] = $value;
        } else if(is_null($value) || empty($value)) {
            $this->attributes['client'] = null;
        } else {
            $this->attributes['client'] = 'str_' . $value;
        }
    }

    public function getClientAttribute($value): User|string|null
    {
        if (ctype_digit($value)) {
            return User::find((int) $value);
        }

        return $value ? substr($value, 4) : null; // удаляем префикс "str_" перед возвратом значения
    }
}
