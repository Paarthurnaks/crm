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
use App\Http\Filters\SectionFilter;
use App\Services\SectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

        UserFieldValue::where('entity_id', $deal->id)->where('field_id', $field->id)->update(['value' => (string) $user->id]);
        app(SectionService::class)->setSectionsFieldsValues($section, $deal, 'view');

        $this->assertSame('Иван Иванов', $sectionField->value);
        $this->assertNotNull($sectionField->model);
    }

    public function test_personal_sections_take_priority_over_configured_default_source(): void
    {
        $defaultOwner = User::withoutEvents(fn (): User => User::create([
            'name' => 'Default', 'surname' => 'Owner', 'email' => 'default-section@example.test',
            'password' => 'unused',
        ]));
        $viewer = User::withoutEvents(fn (): User => User::create([
            'name' => 'Personal', 'surname' => 'Owner', 'email' => 'personal-section@example.test',
            'password' => 'unused',
        ]));
        config()->set('sections.default_template_user_id', $defaultOwner->id);
        Section::create(['user_id' => $defaultOwner->id, 'entity' => 'deals', 'title' => 'Default', 'position' => 1]);
        $personal = Section::create(['user_id' => $viewer->id, 'entity' => 'deals', 'title' => 'Personal', 'position' => 1]);
        $filter = new SectionFilter(Request::create('/', 'GET'));
        $service = app(SectionService::class);

        $this->assertSame(['Personal'], $service->getSectionsByEntity('deals', 999999, $filter, [], $viewer)->pluck('title')->all());

        $personal->delete();
        $this->assertSame(['Default'], $service->getSectionsByEntity('deals', 999999, $filter, [], $viewer)->pluck('title')->all());
    }

    public function test_sections_keep_position_order_and_first_duplicate_title(): void
    {
        $viewer = User::withoutEvents(fn (): User => User::create([
            'name' => 'Section', 'surname' => 'Viewer', 'email' => 'section-order@example.test',
            'password' => 'unused',
        ]));
        $last = Section::create(['user_id' => $viewer->id, 'entity' => 'deals', 'title' => 'Last', 'position' => 3]);
        $first = Section::create(['user_id' => $viewer->id, 'entity' => 'deals', 'title' => 'Repeated', 'position' => 1]);
        Section::create(['user_id' => $viewer->id, 'entity' => 'deals', 'title' => 'Repeated', 'position' => 2]);

        $sections = app(SectionService::class)->getSectionsByEntity(
            'deals', 999999, new SectionFilter(Request::create('/', 'GET')), [], $viewer
        );

        $this->assertSame([$first->id, $last->id], $sections->pluck('id')->values()->all());
        $this->assertSame(['Repeated', 'Last'], $sections->pluck('title')->values()->all());
    }

    public function test_section_create_ignores_unvalidated_owner_id(): void
    {
        $actor = User::withoutEvents(fn (): User => User::create([
            'name' => 'Actor', 'surname' => 'Owner', 'email' => 'section-actor@example.test',
            'password' => 'unused',
        ]));
        $other = User::withoutEvents(fn (): User => User::create([
            'name' => 'Other', 'surname' => 'Owner', 'email' => 'section-other@example.test',
            'password' => 'unused',
        ]));

        $this->actingAs($actor, 'api')->postJson('/api/sections', [
            'entity' => 'deals',
            'title' => 'Owned',
            'position' => 1,
            'section_fields' => [],
            'user_id' => $other->id,
        ])->assertCreated();

        $this->assertDatabaseHas('sections', ['title' => 'Owned', 'user_id' => $actor->id]);
    }
}
