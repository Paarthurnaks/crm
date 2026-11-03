<?php

namespace App\Models;

use App\Enums\UserFieldTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @OA\Schema(
 *     title="SectionField",
 *     description="Поле раздела",
 *
 *     @OA\Xml(
 *         name="SectionField"
 *     )
 * )
 *
 * @property int $id
 * @property int $section_id
 * @property int|null $field_id
 * @property int|null $user_field_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Section|null $section
 * @property-read Field|null $field
 * @property-read UserField|null $userField
 * @property mixed $value
 * @property mixed $model
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 *
 * @mixin Builder<static>
 */
class SectionField extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'field_id',
        'user_field_id',
        'position',
    ];

    protected $appends = [
        'value',
        'model',
    ];

    /**
     * @OA\Property(
     *     title="ID",
     *     description="ID",
     *     format="int64",
     *     example=1
     * )
     *
     * @var int
     */
    private $id;

    /**
     * @OA\Property(
     *     title="section_id",
     *     description="id раздела",
     *     format="int64",
     *     example=1
     * )
     *
     * @var int
     */
    private $section_id;

    /**
     * @OA\Property(
     *      title="title",
     *      description="код поля",
     *      example="responsible_id"
     * )
     *
     * @var string
     */
    private $code;

    /**
     * @OA\Property(
     *      title="position",
     *      description="позиция поля (для сортировки)",
     *      example=1
     * )
     *
     * @var int
     */
    private $position;

    private $value;

    private $model;

    protected function getValueAttribute()
    {
        return $this->value;
    }

    protected function setValueAttribute($value)
    {
        $this->value = $value;
    }

    protected function getModelAttribute()
    {
        return $this->model;
    }

    protected function setModelAttribute($model)
    {
        $this->model = $model;
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function userField(): BelongsTo
    {
        return $this->belongsTo(UserField::class);
    }

    public function getUserFieldValue(string $entityId): null|array|string
    {
        $fieldId = $this->user_field_id ?? $this->field_id;

        $userFieldValue = null;

        if ($fieldId) {
            $userFieldValue = UserFieldValue::where('entity_id', $entityId)->where('field_id', $fieldId)->first();
        }

        if ($this->userField->type == UserFieldTypeEnum::FILE->value) {
            return collect($userFieldValue?->getMedia())->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'url' => $attachment->original_url,
                ];
            })->toArray();
        }

        return $userFieldValue?->value;
    }
}
