<?php

namespace App\Traits;

use App\Http\Resources\UserFieldValueResource;
use App\Models\UserFieldValue;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasUserFields
{
    public function userFieldValues(): HasMany
    {
        $entity = strtolower(class_basename($this).'s');

        return $this->hasMany(
            UserFieldValue::class,
            'entity_id',
            'id'
        )->whereHas('userField', function ($query) use ($entity) {
            $query->where('entity', '=', $entity);
        });
    }

    public function setCustomFieldValue($fieldName, $value): void
    {
        $field = $this->userFieldValues()->firstOrNew(['field_id' => $fieldName]);
        $field->value = $value;
        $field->save();
    }

    public function getCustomFieldValue($field)
    {
        $fieldValue = $this->userFieldValues()->where('field_id', $field->id)->first();

        if ($field->type == 'file') {
            return UserFieldValueResource::make($fieldValue);
        }

        return $fieldValue ? $fieldValue->value : null;
    }
}
