<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     title="UserTableSettings",
 *     description="Настройка таблиц у пользователя",
 *
 *     @OA\Xml(
 *         name="UserTableSettings"
 *     )
 * )
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
}
