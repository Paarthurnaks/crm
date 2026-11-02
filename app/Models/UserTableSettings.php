<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     title="UserTableSettings",
 *     description="Настройка таблиц у пользователя",
 *
 *     @OA\Xml(
 *         name="UserTableSettings"
 *     )
 * )
 *
 * @property int $id
 * @property string $entity
 * @property int $user_id
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class UserTableSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity',
        'user_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * @OA\Property(
     *     title="ID",
     *     description="ID",
     *     format="int64",
     *     example=1
     * )
     */
    private int $id;

    /**
     * @OA\Property(
     *     title="entity",
     *     description="сущность (deals, tasks, users, accounts, documents)",
     *     example="deal"
     * )
     */
    private string $entity;

    /**
     * @OA\Property(
     *     title="settings",
     *     description="настройки таблицы",
     *     property="settings",
     *     type="object",
     *     @OA\Property(
     *         property="on_page",
     *         example=20
     *     )
     * )
     *
     * @var array
     */
    private string $settings;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
