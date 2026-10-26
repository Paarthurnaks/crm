<?php

namespace App\Models;

use App\Http\Filters\Traits\Filterable;
use App\Traits\HasChats;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property Role $role
 *
 * @OA\Schema(
 *     title="User",
 *     description="Пользователь",
 *
 *     @OA\Xml(
 *         name="User"
 *     )
 * )
 */
class User extends Authenticatable implements HasMedia, JWTSubject
{
    use Filterable,
        HasApiTokens,
        HasChats,
        HasFactory,
        InteractsWithMedia,
        Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'password',
        'color',
        'role_id',
        'division_id',
        'active',
        'last_login',
        'post',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_high_level_admin' => 'boolean',
    ];

    protected $appends = [
        'is_high_level_admin',
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
     *      title="email",
     *      description="email пользователя",
     *      example="example@gmail.com"
     * )
     *
     * @var string
     */
    private $email;

    /**
     * @OA\Property(
     *      title="name",
     *      description="Имя пользователя",
     *      example="Ivan"
     * )
     *
     * @var string
     */
    private $name;

    /**
     * @OA\Property(
     *      title="surname",
     *      description="Фамилия пользователя",
     *      example="Ivanov"
     * )
     *
     * @var string
     */
    private $surname;

    /**
     * @OA\Property(
     *     title="role_id",
     *     description="id роли",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bigInteger
     */
    private $role_id;

    /**
     * @OA\Property(
     *     title="division_id",
     *     description="id отдела",
     *     format="int64",
     *     example=1
     * )
     *
     * @var bigInteger
     */
    private $division_id;

    /**
     * @OA\Property(
     *     title="active",
     *     description="активный",
     *     format="bool",
     *     example=true
     * )
     *
     * @var bool
     */
    private $active;

    /**
     * @OA\Property(
     *     title="last_login",
     *     description="последняя авторизация",
     *     format="datetime",
     *     example="2022-12-27 10:09:20"
     * )
     *
     * @var datetime
     */
    private $last_login;

    /**
     * @OA\Property(
     *     title="created_at",
     *     description="дата создания",
     *     format="datetime",
     *     example="2022-12-27 10:09:20"
     * )
     *
     * @var datetime
     */
    private $created_at;

    /**
     * @OA\Property(
     *     title="updated_at",
     *     description="дата обновления",
     *     format="datetime",
     *     example="2022-12-27 10:09:20"
     * )
     *
     * @var datetime
     */
    private $updated_at;

    /**
     * @OA\Property(
     *      title="post",
     *      description="должность пользователя",
     *      example="Бухгалтер"
     * )
     *
     * @var string
     */
    private $post;

    /**
     * @OA\Property(
     *      title="is_high_level_admin",
     *      description="является ли пользователь главным админом",
     *      format="bool",
     *      example="false"
     * )
     *
     * @var bool
     */
    private $is_high_level_admin;

    public function highLevelAdmin(): HasOne
    {
        return $this->hasOne(HighLevelAdmin::class);
    }

    public function getIsHighLevelAdminAttribute(): bool
    {
        return $this->relationLoaded('highLevelAdmin')
            ? $this->highLevelAdmin !== null
            : $this->highLevelAdmin()->exists();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function partnerDeals(): HasMany
    {
        return $this->hasMany(Deal::class, 'client', 'partner_deal_client_key');
    }

    public function getPartnerDealClientKeyAttribute(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('preview')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function hasDealAccess(int $dealId): bool
    {
        $deal = Deal::find($dealId);

        return $deal !== null && Gate::forUser($this)->allows('view', $deal);
    }

    public function hasAccess(string $entity, string $action, string $scope): bool
    {
        return $this->permissionScope($entity, $action) === $scope;
    }

    public function permissionScope(string $entity, string $action): ?string
    {
        if (! $this->getAttribute('role_id') || ! in_array($action, ['view', 'create', 'update', 'delete', 'import', 'export'], true)) {
            return null;
        }

        return $this->role->permissions()
            ->where('entity', $entity)
            ->orderByDesc('permissions.id')
            ->pluck($action)
            ->first();
    }

    /**
     * @param  mixed  $roles
     */
    public function hasRole(array|string $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return $this->role()
            ->whereIn('original_title', $roles)
            ->orWhereIn('title', $roles)
            ->exists();
    }

    public function clients(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            PartnerClient::class,
            'partner_id',
            'id',
            'id',
            'client_id',
        );
    }

    public function partner(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            PartnerClient::class,
            'client_id',
            'id',
            'id',
            'partner_id'
        );
    }
}
