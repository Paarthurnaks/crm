<?php

namespace App\Models;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultChatNameEnum;
use App\Enums\DefaultRolesEnum;
use App\Http\Filters\Traits\Filterable;
use App\Models\Scopes\ChatsScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @OA\Schema(
 *     title="Chat",
 *     description="Модель чата",
 *
 *     @OA\Xml(name="Chat"),
 *
 *     @OA\Property(
 *         property="id",
 *         title="ID",
 *         description="Идентификатор чата",
 *         format="int64",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="type",
 *         title="Type",
 *         description="Тип чата, равный single или group",
 *         type="string",
 *         enum={"single", "group"},
 *         example="single"
 *     ),
 *     @OA\Property(
 *         property="avatar",
 *         title="Avatar",
 *         description="Ссылка на аватар чата",
 *         type="string",
 *         example="/storage/chats/asdkasDUasndASAPS934kmd.jpg"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         title="Description",
 *         description="Описание чата, актуально только для type=group",
 *         type="string",
 *         example="Чат для работников завода.."
 *     ),
 *     @OA\Property(
 *          property="category",
 *          title="Category",
 *          description="Категория чата",
 *          type="string",
 *          enum={"system", "partner", "client"},
 *          example="system"
 *      )
 * )
 *
 * @property int $id
 * @property string $type
 * @property string|null $name
 * @property string|null $description
 * @property string|null $avatar
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatMessage> $messages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatMember> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UnreadChatMessage> $unreadMessages
 * @property-read \App\Models\ChatMessage|null $lastMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatCategory> $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatCategory> $categories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @method static \Illuminate\Database\Eloquent\Builder<static> filter(\App\Http\Filters\Abstract\QueryFilter $filter)
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Chat extends Model implements HasMedia
{
    use Filterable, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'type',
        'name',
        'description',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(new ChatsScope);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatMember::class);
    }

    public function unreadMessages(): HasMany
    {
        return $this->hasMany(UnreadChatMessage::class, 'chat_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->with('chat')->latest();
    }

    public function getDisplayName($userId): string
    {
        return match ($this->type) {
            ChatTypeEnum::SINGLE->value => $this->getOtherUserName($userId) ?? DefaultChatNameEnum::DELETED->value,
            ChatTypeEnum::DEFAULT->value => DefaultChatNameEnum::DEFAULT->value,
            ChatTypeEnum::PARTNER->value => $this->getPartnerChatName($userId),
            ChatTypeEnum::CLIENT->value => $this->getClientChatName($userId),
            ChatTypeEnum::GROUP->value => $this->name,
        };
    }

    private function getClientChatName($userId): string
    {
        $user = $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', $userId)?->user
            : User::find($userId);
        $user ??= auth()->user();

        if ($user && $this->userHasRole($user, DefaultRolesEnum::havePersonalAccount())) {
            return $this->getOtherUserName($userId) ?? DefaultChatNameEnum::DELETED->value;
        }

        if ($this->relationLoaded('members')) {
            $partnerUser = $this->members->first(fn ($member) => $member->user?->role?->original_title === DefaultRolesEnum::PARTNER->value)?->user;
            $clientUser = $this->members->first(fn ($member) => $member->user?->role?->original_title === DefaultRolesEnum::CLIENT->value)?->user;
        } else {
            $partnerUser = $this->members()->whereHas('user.role', fn ($query) => $query->where('original_title', DefaultRolesEnum::PARTNER->value))->with('user')->first()?->user;
            $clientUser = $this->members()->whereHas('user.role', fn ($query) => $query->where('original_title', DefaultRolesEnum::CLIENT->value))->with('user')->first()?->user;
        }

        $partnerName = $partnerUser ? "{$partnerUser->name} {$partnerUser->surname}" : DefaultChatNameEnum::DELETED_PARTNER->value;
        $clientName = $clientUser ? "{$clientUser->name} {$clientUser->surname}" : DefaultChatNameEnum::DELETED_CLIENT->value;

        return "$partnerName — $clientName";
    }

    private function getOtherUserName($userId): ?string
    {
        if ($this->relationLoaded('members')) {
            $otherUser = $this->members
                ->filter(fn ($member) => (int) $member->user_id !== (int) $userId)
                ->first(function ($member) {
                    return match ($this->type) {
                        ChatTypeEnum::CLIENT->value => $member->user?->role?->original_title === DefaultRolesEnum::CLIENT->value,
                        ChatTypeEnum::PARTNER->value => $member->user?->role?->original_title === DefaultRolesEnum::PARTNER->value,
                        default => true,
                    };
                })?->user;

            return $otherUser ? "{$otherUser->name} {$otherUser->surname}" : DefaultChatNameEnum::DELETED->value;
        }

        $query = $this->members()
            ->where('user_id', '!=', $userId)
            ->with(['user']);

        if ($this->type === ChatTypeEnum::CLIENT->value) {
            $query->whereHas('user.role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::CLIENT->value);
            });
        } elseif ($this->type === ChatTypeEnum::PARTNER->value) {
            $query->whereHas('user.role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::PARTNER->value);
            });
        }

        $otherUser = $query->first()?->user;

        return $otherUser ? "{$otherUser->name} {$otherUser->surname}" : DefaultChatNameEnum::DELETED->value;
    }

    private function getPartnerChatName($userId): string
    {
        $user = $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', $userId)?->user
            : User::find($userId);

        if ($user && $this->userHasRole($user, [DefaultRolesEnum::PARTNER->value])) {
            return DefaultChatNameEnum::ESB_SYSTEM->value;
        }

        return $this->getOtherUserName($userId) ?? DefaultChatNameEnum::DELETED->value;
    }

    public function getDisplayAvatar($userId): string
    {
        if ($this->type === ChatTypeEnum::SINGLE->value) {
            $otherUser = $this->relationLoaded('members')
                ? $this->members->first(fn ($member) => (int) $member->user_id !== (int) $userId)?->user
                : $this->members()->where('user_id', '!=', $userId)->with('user')->first()?->user;

            return $otherUser?->getFirstMedia()?->getUrl() ?? asset(Storage::url('avatars/avatar.png'));
        }

        if ($this->type === ChatTypeEnum::PARTNER->value) {
            $partner = $this->relationLoaded('members')
                ? $this->members->firstWhere('user_id', $userId)?->user
                : User::find($userId);

            if ($partner && $this->userHasRole($partner, [DefaultRolesEnum::PARTNER->value])) {
                return asset(Storage::url('avatars/esb_logo.png'));
            }
        }

        return $this->getFirstMedia()?->getUrl() ?? asset(Storage::url('avatars/chat-avatar.png'));
    }

    private function userHasRole(User $user, array $roles): bool
    {
        $role = $user->role;

        return $role !== null && (in_array($role->original_title, $roles, true) || in_array($role->title, $roles, true));
    }

    public function category(): BelongsToMany
    {
        return $this->belongsToMany(ChatCategory::class, 'chat_category_mappings', 'chat_id', 'chat_category_id')
            ->withPivot('user_id')
            ->wherePivot('user_id', auth()->id())
            ->limit(1);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ChatCategory::class, 'chat_category_mappings', 'chat_id', 'chat_category_id');
    }

    public function isReadByUser(?int $userId = null): bool
    {
        if ($userId === null) {
            return false;
        }

        return ! $this->unreadMessages()->where('user_id', $userId)->exists();
    }
}
