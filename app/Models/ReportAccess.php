<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $report_id
 * @property int $user_id
 *
 * @OA\Schema(
 *     title="ReportAccess",
 *     description="Кто имеет доступ к отчёту",
 *
 *     @OA\Xml(
 *         name="ReportAccess"
 *     )
 * )
 */
class ReportAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'user_id',
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
     *      title="report_id",
     *      description="id отчета",
     *      type="int",
     *      example=13
     * )
     */
    private float $report_id;

    /**
     * @OA\Property(
     *     title="user_id",
     *     description="id пользователя",
     *     type="int",
     *     example=1
     * )
     */
    private int $user_id;
}
