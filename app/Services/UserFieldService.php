<?php

namespace App\Services;

use App\Enums\EntityTypeEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Enums\UserFieldTypeEnum;
use App\Http\Filters\UserFieldFilter;
use App\Models\Deal;
use App\Models\SalesStage;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldEnumValue;
use App\Models\UserFieldValue;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UserFieldService
{
    private const ERROR_MESSAGES = [
        'createError' => 'Произошла ошибка во время создания пользовательского поля',
        'updateError' => 'Произошла ошибка во время обновления пользовательского поля',
    ];

    public function addAttachments(int $entityId, int $fieldId, array $attachments): UserFieldValue
    {
        $value = UserFieldValue::firstOrCreate(
            ['entity_id' => $entityId, 'field_id' => $fieldId],
            ['value' => null]
        );
        $createdMedia = [];

        try {
            foreach ($attachments as $attachment) {
                $createdMedia[] = $value->addMedia($attachment)->toMediaCollection();
            }
        } catch (\Throwable $exception) {
            foreach ($createdMedia as $media) {
                try {
                    $media->delete();
                } catch (\Throwable $cleanupException) {
                    Log::error('user_field.attachment.cleanup.failed', ['value_id' => $value->id, 'media_id' => $media->id, 'exception' => $cleanupException::class]);
                }
            }
            if ($value->wasRecentlyCreated) {
                try {
                    $value->delete();
                } catch (\Throwable $cleanupException) {
                    Log::error('user_field.value.cleanup.failed', ['value_id' => $value->id, 'exception' => $cleanupException::class]);
                }
            }
            Log::error('user_field.attachments.add.failed', ['value_id' => $value->id, 'exception' => $exception::class]);
            throw $exception;
        }

        Log::info('user_field.attachments.added', ['value_id' => $value->id, 'count' => count($createdMedia)]);

        return $value->refresh();
    }

    public function deleteAttachment(int $entityId, int $fieldId, int $mediaId): ?UserFieldValue
    {
        $value = UserFieldValue::where('entity_id', $entityId)
            ->where('field_id', $fieldId)
            ->first();

        if (! $value) {
            Log::warning('user_field.attachment.value_missing', ['entity_id' => $entityId, 'field_id' => $fieldId]);

            return null;
        }

        /** @var Media|null $media */
        $media = $value->media()->find($mediaId);
        if (! $media) {
            Log::warning('user_field.attachment.missing', ['value_id' => $value->id, 'media_id' => $mediaId]);

            return null;
        }

        $value->deleteMedia($media);
        Log::info('user_field.attachment.deleted', ['value_id' => $value->id, 'media_id' => $mediaId]);

        return $value->refresh();
    }

    public function getList(UserFieldFilter $filter, array $queryParams = []): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        return UserField::filter($filter)
            ->with('userFieldEnumValues')
            ->paginate($limit);
    }

    /**
     * @throws Exception
     */
    public function create(array $data): UserField
    {
        try {
            return DB::transaction(function () use ($data) {
                $userField = UserField::create([
                    'entity' => $data['entity'],
                    'name' => $data['name'],
                    'type' => $data['type'],
                ]);

                $code = 'uf_'.$data['entity'].'_'.$userField->id;
                $userField->update(['code' => $code]);

                if ($data['type'] === UserFieldTypeEnum::ENUM->value) {
                    $options = collect($data['options'])->map(function ($option) {
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
            throw new Exception($this::ERROR_MESSAGES['createError'], 0, $e);
        }
    }

    /**
     * @throws Exception
     */
    public function update(UserField $userField, array $data): UserField
    {
        try {
            return DB::transaction(function () use ($data, $userField) {
                $userField->update(array_intersect_key($data, array_flip(['entity', 'name', 'type'])));

                if ($userField->type === UserFieldTypeEnum::ENUM->value && array_key_exists('options', $data)) {
                    $existingOptions = $userField->userFieldEnumValues()->get()->keyBy('id');
                    $options = collect($data['options'])->map(function ($option) use ($existingOptions) {
                        if (isset($option['id'])) {
                            $enumValue = $existingOptions->get($option['id']);
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

                    $userField->userFieldEnumValues()->whereNotIn('id', collect($data['options'])
                        ->pluck('id')
                        ->filter())
                        ->delete();
                    $userField->userFieldEnumValues()->saveMany($options);
                }

                return $userField;
            });
        } catch (\Throwable $e) {
            throw new Exception($this::ERROR_MESSAGES['updateError'], 0, $e);
        }
    }

    public function delete(UserField $field): void
    {
        $field->delete();
    }

    public function updateUserFieldEntity(Deal $model, array $data, SectionFieldEntityEnum $entityEnum): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'user_field_values') {
                $this->addUserFieldValues($model->id, $value, $entityEnum);
            } elseif ($key === 'sales_funnel_id') {
                $model->sales_funnel_id = $value;
                $salesStage = SalesStage::query()
                    ->where('sales_funnel_id', $value)
                    ->where('position', 1)
                    ->first()
                    ?? SalesStage::query()->where('sales_funnel_id', $value)->firstOrFail();
                $model->sales_stage_id = $salesStage->id;
            } elseif ($key === 'responsible_id' && $model->client instanceof User && $model->client->partner && $value !== $model->client->partner->id) {
                $model->client = null;
            } elseif ($key === 'client_id') {
                $client = is_int($value) ? User::find($value) : null;

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

    public function addUserFieldValues(int $entityId, array $fieldValues, SectionFieldEntityEnum $entityEnum): void
    {
        $fieldsByCode = UserField::where('entity', $entityEnum->value)
            ->whereIn('code', array_keys($fieldValues))
            ->get()
            ->keyBy('code');

        foreach ($fieldValues as $ufName => $ufValue) {
            $userField = $fieldsByCode->get($ufName);

            if (! $userField) {
                continue;
            }

            UserFieldValue::updateOrCreate([
                'field_id' => $userField->id,
                'entity_id' => $entityId,
            ], [
                'value' => $ufValue,
            ]);
        }

        Log::debug('user_field.values.saved', ['entity_id' => $entityId, 'field_count' => $fieldsByCode->count()]);
    }

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
