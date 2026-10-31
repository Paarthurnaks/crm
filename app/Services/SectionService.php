<?php

namespace App\Services;

use App\Enums\EntityTypeEnum;
use App\Enums\ProhibitedFieldEnum;
use App\Http\Filters\SectionFilter;
use App\Http\Resources\DealResource;
use App\Http\Resources\UserResource;
use App\Models\Deal;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SectionService
{
    public function __construct(private SectionFieldService $sectionFieldService) {}

    public function getSectionsByEntity(string $entity, int $id, SectionFilter $sectionFilter, array $queryParams, User $actor): EloquentCollection
    {
        $sections = $this->sectionQuery($entity, $actor->id, $sectionFilter)->get();

        if ($sections->count() === 0) {
            $sections = $this->sectionQuery($entity, config('sections.default_template_user_id'), $sectionFilter)->get();
            $source = 'default';
        } else {
            $source = 'personal';
        }
        Log::debug('section.source.selected', ['entity' => $entity, 'actor_id' => $actor->id, 'source' => $source]);

        $seenTitles = [];
        $sections = $sections->filter(function ($section) use (&$seenTitles) {
            if (! isset($seenTitles[$section->title])) {
                $seenTitles[$section->title] = true;

                return true;
            }

            return false;
        });

        if ($entity == 'deals') {
            $entityObj = Deal::find($id);
        } else {
            $entityObj = null;
        }

        try {
            $related = $entityObj ? $this->prepareRelatedFieldValues($sections, $entityObj) : null;

            foreach ($sections as $section) {
                $this->setSectionsFieldsValues($section, $entityObj, $queryParams['type'] ?? 'view', $related);
            }
        } catch (\Throwable $exception) {
            Log::error('section.values.failed', ['entity' => $entity, 'entity_id' => $id, 'exception' => $exception::class]);
            throw $exception;
        }

        return $sections;
    }

    private function sectionQuery(string $entity, int $userId, SectionFilter $filter): Builder
    {
        return Section::query()
            ->where('entity', $entity)
            ->where('is_template', false)
            ->orderBy('position')
            ->where('user_id', $userId)
            ->filter($filter)
            ->with('fields.userField', 'fields.field');
    }

    public function setSectionsFieldsValues(Section $section, $entityObj, string $type, ?array $related = null): void
    {
        $related ??= $entityObj ? $this->prepareRelatedFieldValues(collect([$section]), $entityObj) : [];
        if (! $entityObj) {
            return;
        }

        foreach ($this->availableFields($section, $type) as $sectionField) {
            if ($sectionField->userField) {
                $this->setUserFieldValue($sectionField, $entityObj, $related);
            } elseif ($sectionField->field) {
                $this->setSystemFieldValue($sectionField, $entityObj);
            }
        }
    }

    private function availableFields(Section $section, string $type): EloquentCollection
    {
        if ($type === 'view') {
            return $section->fields;
        }

        return $section->fields()->whereDoesntHave('field', function ($query) {
            $query->whereIn('code', ProhibitedFieldEnum::values());
        })->with('userField', 'field')->get();
    }

    private function setUserFieldValue(SectionField $sectionField, Deal $deal, array $related): void
    {
        $sectionField->value = $related['values'][$sectionField->id]
            ?? $sectionField->getUserFieldValue($deal->id);
        if ($sectionField->userField->type === 'user') {
            $this->setRelatedUserValue($sectionField, $related['users']);

            return;
        }

        if ($sectionField->userField->type === 'deal') {
            $this->setRelatedDealValue($sectionField, $related['deals']);
        }
    }

    private function setRelatedUserValue(SectionField $sectionField, array $users): void
    {
        $user = $users[(int) $sectionField->value] ?? null;
        if ($user) {
            $sectionField->model = UserResource::make($user);
            $sectionField->value = $user->name.' '.$user->surname;
        } elseif ($sectionField->value) {
            Log::warning('section.related_user.missing', ['section_field_id' => $sectionField->id]);
        }
    }

    private function setRelatedDealValue(SectionField $sectionField, array $deals): void
    {
        $relatedDeal = $deals[(int) $sectionField->value] ?? null;
        if ($relatedDeal) {
            $sectionField->model = DealResource::make($relatedDeal);
            $sectionField->value = $relatedDeal->title;
        } elseif ($sectionField->value) {
            Log::warning('section.related_deal.missing', ['section_field_id' => $sectionField->id]);
        }
    }

    private function setSystemFieldValue(SectionField $sectionField, Deal $deal): void
    {
        $code = $sectionField->field->code;
        if (in_array($code, ['client', 'responsible', 'creator', 'who_changed'], true)) {
            $relatedUser = $deal->{$code};
            if ($relatedUser instanceof User) {
                $sectionField->model = UserResource::make($relatedUser);
                $sectionField->value = $relatedUser->name.' '.$relatedUser->surname;
            } else {
                $sectionField->value = $relatedUser;
            }

            return;
        }

        if ($code === 'sales_stage' || $code === 'sales_funnel') {
            $relation = $code === 'sales_stage' ? 'salesStage' : 'salesFunnel';
            $relatedModel = $deal->{$relation};
            $sectionField->model = $relatedModel;
            $sectionField->value = $relatedModel?->title;

            return;
        }

        $sectionField->value = $deal->{$code};
    }

    private function prepareRelatedFieldValues($sections, $entityObj): array
    {
        $values = [];
        $userIds = [];
        $dealIds = [];

        foreach ($sections as $section) {
            foreach ($section->fields as $sectionField) {
                $relatedType = $sectionField->userField?->type;
                if (! in_array($relatedType, ['user', 'deal'], true)) {
                    continue;
                }

                $value = $sectionField->getUserFieldValue($entityObj->id);
                $values[$sectionField->id] = $value;

                if (! is_numeric($value)) {
                    continue;
                }

                if ($relatedType === 'user') {
                    $userIds[] = (int) $value;
                } else {
                    $dealIds[] = (int) $value;
                }
            }
        }

        $users = User::whereIn('id', array_unique($userIds))->get()->keyBy('id')->all();
        $deals = Deal::whereIn('id', array_unique($dealIds))->get()->keyBy('id')->all();

        Log::debug('section.related_values.prepared', [
            'section_count' => $sections->count(),
            'user_count' => count($users),
            'deal_count' => count($deals),
        ]);

        return compact('values', 'users', 'deals');
    }

    public function createSection(array $sectionData, array $queryParams, User $actor): Section
    {
        $section = DB::transaction(function () use ($sectionData, $queryParams, $actor) {
            $section = Section::create([
                'user_id' => $actor->id,
                'is_template' => $queryParams['from_create_page'] ?? false,
                'deal_id' => ($sectionData['entity'] == 'deals' && isset($queryParams['entity_id'])) ? $queryParams['entity_id'] : null,
                'entity' => $sectionData['entity'],
                'title' => $sectionData['title'] ?? 'Новый раздел',
                'position' => $sectionData['position'],
                'sales_funnel_id' => $sectionData['sales_funnel_id'] ?? null,
                'is_customized' => isset($sectionData['sales_funnel_id']),
            ]);

            if (isset($sectionData['sales_funnel_id'])) {
                $this->switchFunnelSectionsToCustomized($sectionData['sales_funnel_id'], $actor);
            }

            foreach ($sectionData['section_fields'] as $sectionFieldData) {
                $this->sectionFieldService->createSectionField($sectionFieldData, $section->id);
            }

            return $section;
        });

        $entityObj = null;
        if (isset($queryParams['entity_id']) && $section->entity == 'deals') {
            $entityObj = Deal::find($queryParams['entity_id']);
        }
        $this->setSectionsFieldsValues($section, $entityObj, 'view');

        return $section;
    }

    private function switchFunnelSectionsToCustomized(string $salesFunnelId, User $actor): void
    {
        Section::where('sales_funnel_id', $salesFunnelId)
            ->where('is_template', true)
            ->where('user_id', $actor->id)
            ->update(['is_customized' => true]);
    }

    public function updateSection(Section $section, array $sectionData, array $queryParams): Section
    {
        DB::transaction(function () use ($section, $sectionData) {
            if (isset($sectionData['title'])) {
                $section->update(['title' => $sectionData['title']]);
            }
            if (isset($sectionData['position'])) {
                $section->update(['position' => $sectionData['position']]);
            }
            $arrayFieldsIds = [];
            if (isset($sectionData['section_fields'])) {
                foreach ($sectionData['section_fields'] as $sectionFieldData) {
                    if (array_key_exists('id', $sectionFieldData)) {
                        $arrayFieldsIds[] = $sectionFieldData['id'];
                        $field = SectionField::find($sectionFieldData['id']);
                        if ($field) {
                            $this->sectionFieldService->updateSectionField($field, $sectionFieldData);
                        }
                    } else {
                        $field = $this->sectionFieldService->createSectionField($sectionFieldData, $section->id);
                        $arrayFieldsIds[] = $field->id;
                    }
                }
                $this->syncFields($section, $arrayFieldsIds);
            }
        });

        $entityObj = null;
        if ($section->entity === EntityTypeEnum::DEALS->value) {
            $entityObj = Deal::find($section->deal_id);
        }

        $this->setSectionsFieldsValues($section, $entityObj, $queryParams['type'] ?? 'view');

        return $section;

    }

    public function deleteSection(Section $section): void
    {
        $section->delete();
    }

    /**
     * @param  array  $arrayFieldsIds  @arrayFieldsIds
     * @return void $section
     */
    private function syncFields(Section $section, array $arrayFieldsIds): void
    {
        foreach ($section->fields as $field) {
            if (! in_array($field->id, $arrayFieldsIds)) {
                $field->delete();
            }
        }

        $section->refresh();

    }
}
