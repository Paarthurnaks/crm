<?php

namespace Tests\Feature;

use App\Enums\UserFieldTypeEnum;
use App\Models\UserField;
use App\Models\UserFieldEnumValue;
use App\Services\UserFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFieldOptionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_does_not_modify_another_fields_enum_option(): void
    {
        $first = UserField::create(['entity' => 'deals', 'name' => 'First', 'type' => UserFieldTypeEnum::ENUM->value]);
        $second = UserField::create(['entity' => 'deals', 'name' => 'Second', 'type' => UserFieldTypeEnum::ENUM->value]);
        $otherOption = UserFieldEnumValue::create([
            'field_id' => $second->id,
            'option' => 'Original',
            'sort_order' => 1,
        ]);

        app(UserFieldService::class)->update($first, [
            'entity' => 'deals',
            'name' => 'First',
            'type' => UserFieldTypeEnum::ENUM->value,
            'options' => [['id' => $otherOption->id, 'option' => 'Changed', 'sort_order' => 1]],
        ]);

        $this->assertSame('Original', $otherOption->fresh()->option);
        $this->assertSame('Changed', $first->userFieldEnumValues()->firstOrFail()->option);
    }
}
