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
 * @property $id
 * @property $type
 * @property $name
 * @property $description
 * @property $category
 *
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
        $user = User::find($userId) ?? auth()->user();

        if ($user && $user->hasRole(DefaultRolesEnum::havePersonalAccount())) {
            return $this->getOtherUserName($userId) ?? DefaultChatNameEnum::DELETED->value;
        }

        $partnerUser = $this->members()
            ->whereHas('user.role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::PARTNER->value);
            })
            ->with(['user'])
            ->first()
            ?->user;

        $clientUser = $this->members()
            ->whereHas('user.role', function ($query) {
                $query->where('original_title', DefaultRolesEnum::CLIENT->value);
            })
            ->with(['user'])
            ->first()
            ?->user;

        $partnerName = $partnerUser ? "{$partnerUser->name} {$partnerUser->surname}" : DefaultChatNameEnum::DELETED_PARTNER->value;
        $clientName = $clientUser ? "{$clientUser->name} {$clientUser->surname}" : DefaultChatNameEnum::DELETED_CLIENT->value;

        return "$partnerName — $clientName";
    }

    private function getOtherUserName($userId): ?string
    {
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
        $user = User::find($userId);

        if ($user && $user->hasRole(DefaultRolesEnum::PARTNER->value)) {
            return DefaultChatNameEnum::ESB_SYSTEM->value;
        }

        return $this->getOtherUserName($userId) ?? DefaultChatNameEnum::DELETED->value;
    }

    public function getDisplayAvatar($userId): string
    {
        if ($this->type === ChatTypeEnum::SINGLE->value) {
            $otherUser = $this->members()
                ->where('user_id', '!=', $userId)
                ->with(['user'])
                ->first()
                ?->user;

            return $otherUser?->getFirstMedia()?->getUrl() ?? asset(Storage::url('avatars/avatar.png'));
        }

        if ($this->type === ChatTypeEnum::PARTNER->value) {
            $partner = User::find($userId);

            if ($partner->hasRole(DefaultRolesEnum::PARTNER->value)) {
                return asset(Storage::url('avatars/esb_logo.png'));
            }
        }

        return $this->getFirstMedia()?->getUrl() ?? asset(Storage::url('avatars/chat-avatar.png'));
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
