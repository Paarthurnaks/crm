<?php

namespace App\Services;

use App\Enums\EntityTypeEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Enums\UserFieldTypeEnum;
use App\Http\Filters\UserFieldFilter;
use App\Models\Deal;
use App\Models\SalesFunnel;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldEnumValue;
use App\Models\UserFieldValue;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserFieldService
{
    private const ERROR_MESSAGES = [
        'createError' => 'Произошла ошибка во время создания пользовательского поля'
    ];

    /**
     * @param UserFieldFilter $filter
     * @param array $queryParams
     * @return LengthAwarePaginator
     */
    public function getList(UserFieldFilter $filter, array $queryParams = []): LengthAwarePaginator
    {
        $limit = $queryParams["limit"] ?? config("constants.paginator.limit");

        return UserField::filter($filter)
            ->with('userFieldEnumValues')
            ->orWhereDoesntHave('userFieldEnumValues')
            ->paginate($limit);
    }

    /**
     * @param array
     * @return UserField
     * @throws Exception
     */
    public function create(array $data): UserField
    {
        try {
            return DB::transaction(function () use ($data) {
                $userField = UserField::create([
                    "entity" => $data["entity"],
                    "name" => $data["name"],
                    "type" => $data["type"],
                ]);

                $code = 'uf_' . $data["entity"] . '_' . $userField->id;
                $userField->update(['code' => $code]);

                if ($data["type"] === UserFieldTypeEnum::ENUM->value) {
                    $options = collect($data["options"])->map(function ($option) {
                        return new UserFieldEnumValue([
                            'option' => $option['option'],
                            'sort_order' => $option['sort_order'],
                        ]);
                    });

                    $userField->userFieldEnumValues()->saveMany($options);
                }

                return $userField;
            });
        } catch (\Throwable $e) {
            throw new Exception($this::ERROR_MESSAGES['createError']);
        }
    }

    /**
     * @param array $data
     * @param UserField $userField
     * @return UserField
     * @throws Exception
     */
    public function update(UserField $userField, array $data): UserField
    {
        try {
            return DB::transaction(function () use ($data, $userField) {
                $userField->update([
                    "entity" => $data["entity"],
                    "name" => $data["name"],
                    "type" => $data["type"],
                ]);

                if ($data["type"] === UserFieldTypeEnum::ENUM->value) {
                    $options = collect($data["options"])->map(function ($option) {
                        if (isset($option['id'])) {
                            $enumValue = UserFieldEnumValue::find($option['id']);
                            if ($enumValue) {
                                $enumValue->update([
                                    'option' => $option['option'],
                                    'sort_order' => $option['sort_order'],
                                ]);
                                return $enumValue;
                            }
                        }

                        return new UserFieldEnumValue([
                            'option' => $option['option'],
                            'sort_order' => $option['sort_order'],
                        ]);
                    });

                    $userField->userFieldEnumValues()->whereNotIn('id', collect($data["options"])
                        ->pluck('id')
                        ->filter())
                        ->delete();
                    $userField->userFieldEnumValues()->saveMany($options);
                }

                return $userField;
            });
        } catch (\Throwable $e) {
            throw new Exception($this::ERROR_MESSAGES['updateError']);
        }
    }

    /**
     * @param UserField $field
     * @return void
     */
    public function delete(UserField $field): void
    {
        $field->delete();
    }

    /**
     * @param Model $model
     * @param array $data
     * @param SectionFieldEntityEnum $entityEnum
     * @return void
     */
    public function updateUserFieldEntity(Model $model, array $data, SectionFieldEntityEnum $entityEnum): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'user_field_values') {
                $this->addUserFieldValues($model->id, $value, $entityEnum);
            } else if ($key === 'sales_funnel_id') {
                $model->sales_funnel_id = $value;
                $salesFunnel = SalesFunnel::find($value);
                $salesStage = $salesFunnel->salesStages()->where("position", 1)->first();
                $model->sales_stage_id = $salesStage->id ?? $salesFunnel->salesStages()->first()->id;
            } else if ($key === 'responsible_id' && $model->client instanceof User && $model->client->partner && $value !== $model->client->partner->id) {
                $model->client = null;
            } else if ($key === 'client_id') {
                $client = is_integer($value) ? User::find($value) : null;

                if ($client && $client->partner?->id !== $model->responsible_id) {
                    $model->client = null;
                } else {
                    $model->client = $value;
                }
            } else {
                $model->{$key} = $value;
            }
        }
    }

    /**
     * @param int $entityId
     * @param array $fieldValues
     * @param SectionFieldEntityEnum $entityEnum
     * @return void
     */
    public function addUserFieldValues(int $entityId, array $fieldValues, SectionFieldEntityEnum $entityEnum): void
    {
        foreach ($fieldValues as $ufName => $ufValue) {
            $userField = UserField::where('code', $ufName)
                ->where('entity', $entityEnum->value)
                ->first();

            if (!$userField) {
                continue;
            }

            UserFieldValue::updateOrCreate([
                'field_id' => $userField->id,
                'entity_id' => $entityId,
            ], [
                'value' => $ufValue
            ]);
        }
    }

    /**
     * @param UserField|null $userField
     * @return void
     */
    public function setupBooleanValues(?UserField $userField = null): void
    {
        $userFields = $userField
            ? collect([$userField])
            : UserField::where('type', UserFieldTypeEnum::BOOLEAN->value)->get();

        $time = now();

        $newFieldValues = collect([]);

        foreach ($userFields->where('entity', EntityTypeEnum::DEALS->value) as $field) {
            $dealIds = Deal::whereDoesntHave('userFieldValues', function ($query) use ($field) {
                $query->where('field_id', $field->id);
            })->pluck('id');

            if ($dealIds->isEmpty()) {
                continue;
            }

            foreach ($dealIds as $dealId) {
                $newFieldValues->add([
                    'field_id' => $field->id,
                    'entity_id' => $dealId,
                    'value' => 0,
                    'created_at' => $time,
                    'updated_at' => $time,
                ]);
            }
        }


        if ($newFieldValues->isNotEmpty()) {
            UserFieldValue::insert($newFieldValues->toArray());
        }
    }
}
