<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $report_id
 * @property int $field_id
 * @property int $user_field_id
 * @property int|null $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Report|null $report
 * @property-read \App\Models\Field|null $field
 * @property-read \App\Models\UserField|null $userField
 * @property mixed $value
 * @property mixed $model
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ReportField extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'field_id',
        'user_field_id',
        'position',
    ];

    protected $appends = [
        'value',
        'model',
    ];

    protected function getValue()
    {
        return $this->value;
    }

    protected function setValue($value)
    {
        $this->value = $value;
    }

    protected function getModel()
    {
        return $this->model;
    }

    protected function setModel($model)
    {
        $this->model = $model;
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function userField(): BelongsTo
    {
        return $this->belongsTo(UserField::class);
    }
}
