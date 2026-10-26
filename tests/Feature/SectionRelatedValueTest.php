<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\SectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionRelatedValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_related_user_keeps_raw_value_without_failing(): void
    {
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Иван',
            'surname' => 'Иванов',
            'email' => 'section@example.test',
            'password' => 'unused',
        ]));
        $funnel = SalesFunnel::firstOrFail();
        $stage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();
        $deal = Deal::create([
            'title' => 'Сделка',
            'sales_funnel_id' => $funnel->id,
            'sales_stage_id' => $stage->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'note' => '',
        ]);
        $field = UserField::create(['entity' => 'deals', 'name' => 'Пользователь', 'type' => 'user', 'code' => 'linked_user']);
        $section = Section::create(['user_id' => $user->id, 'entity' => 'deals', 'title' => 'Раздел', 'position' => 1]);
        SectionField::create(['section_id' => $section->id, 'user_field_id' => $field->id, 'position' => 1]);
        UserFieldValue::create(['entity_id' => $deal->id, 'field_id' => $field->id, 'value' => '999999']);

        app(SectionService::class)->setSectionsFieldsValues($section, $deal, 'view');

        $sectionField = $section->fields->first();
        $this->assertSame('999999', $sectionField->value);
        $this->assertNull($sectionField->model);
    }
}
