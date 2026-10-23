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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SectionService
{
    public function __construct(private SectionFieldService $sectionFieldService) {}

    public function getSectionsByEntity(string $entity, int $id, SectionFilter $filter, array $queryParams): mixed
    {
        $sections = Section::query()
            ->where('entity', $entity)
            ->where('is_template', false)
            ->orderBy('position')
            ->where('user_id', Auth::user()->id)
            ->filter($filter)
            ->get();

        if ($sections->count() === 0) {
            $sections = Section::query()
                ->where('entity', $entity)
                ->where('is_template', false)
                ->orderBy('position')
                ->where('user_id', 1)
                ->filter($filter)
                ->get();
        }

        $filter = [];
        $sections = $sections->filter(function ($section) use (&$filter) {
            if (! isset($filter[$section->title])) {
                $filter[$section->title] = true;

                return true;
            }

            return false;
        });

        if ($entity == 'deals') {
            $entityObj = Deal::find($id);
        } else {
            $entityObj = null;
        }

        foreach ($sections as $section) {
            $this->setSectionsFieldsValues($section, $entityObj, $queryParams['type'] ?? 'view');
        }

        return $sections;
    }

    public function setSectionsFieldsValues(Section $section, $entityObj, string $type): void
    {
        if ($type !== 'view') {
            $availableFields = $section->fields()->whereDoesntHave('field', function ($query) {
                $query->whereIn('code', ProhibitedFieldEnum::values());
            });
        } else {
            $availableFields = $section->fields;
        }
        foreach ($availableFields as $sectionField) {
            if ($entityObj) {
                if ($sectionField->userField) {
                    $sectionField->value = $sectionField->getUserFieldValue($entityObj->id);
                    if ($sectionField->userField->type == 'user') {
                        $user = User::where('id', $sectionField->value)->first();
                        if ($user) {
                            $sectionField->model = UserResource::make($user);
                            $sectionField->value = $user->name.' '.$user->surname;
                        }
                    } elseif ($sectionField->userField->type == 'deal') {
                        $deal = Deal::where('id', $sectionField->value)->first();
                        if ($deal) {
                            $sectionField->model = DealResource::make($deal);
                            $sectionField->value = $deal->title;
                        }
                    }
                } elseif ($sectionField->field) {
                    if (in_array($sectionField->field->code, ['client', 'responsible', 'creator', 'who_changed'])) {
                        $relatedUser = $entityObj->{$sectionField->field->code};

                        if ($relatedUser instanceof User) {
                            $sectionField->model = UserResource::make($relatedUser);
                            $sectionField->value = $relatedUser->name.' '.$relatedUser->surname;
                        } else {
                            $sectionField->value = $relatedUser;
                        }
                    } elseif ($sectionField->field->code == 'sales_stage' || $sectionField->field->code == 'sales_funnel') {
                        $camelCaseCode = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $sectionField->field->code))));
                        $obj = $entityObj->{$camelCaseCode};
                        $sectionField->model = $obj;
                        $sectionField->value = $obj->title;
                    } else {
                        $sectionField->value = $entityObj->{$sectionField->field->code};

                    }
                }
            }
        }
    }

    public function createSection(array $sectionData, array $queryParams): Section
    {
        $section = DB::transaction(function () use ($sectionData, $queryParams) {
            $section = Section::create([
                'user_id' => Auth::user()->id,
                'is_template' => $queryParams['from_create_page'] ?? false,
                'deal_id' => ($sectionData['entity'] == 'deals' && isset($queryParams['entity_id'])) ? $queryParams['entity_id'] : null,
                'entity' => $sectionData['entity'],
                'title' => $sectionData['title'] ?? 'Новый раздел',
                'position' => $sectionData['position'],
                'sales_funnel_id' => $sectionData['sales_funnel_id'] ?? null,
                'is_customized' => isset($sectionData['sales_funnel_id']),
            ]);

            if (isset($sectionData['sales_funnel_id'])) {
                $this->switchFunnelSectionsToCustomized($sectionData['sales_funnel_id']);
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

    private function switchFunnelSectionsToCustomized(string $salesFunnelId): void
    {
        Section::where('sales_funnel_id', $salesFunnelId)
            ->where('is_template', true)
            ->where('user_id', Auth::user()->id)
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
