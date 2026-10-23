<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportField extends Model
{
    use HasFactory;

    protected $fillable = [
        "report_id",
        "field_id",
        "user_field_id",
        "position"
    ];

    protected $appends = [
        "value",
        "model"
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
