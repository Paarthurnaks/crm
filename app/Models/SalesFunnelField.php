<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $sales_funnel_id
 * @property int|null $field_id
 * @property int|null $user_field_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class SalesFunnelField extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_funnel_id',
        'field_id',
        'user_field_id',
    ];
}
