<?php

namespace Tests\Feature;

use App\Enums\ChatTypeEnum;
use App\Models\ChatMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatCreationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_chat_type_cannot_be_created_through_api(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'api')
            ->postJson('/api/chats', [
                'type' => ChatTypeEnum::DEFAULT->value,
                'user_ids' => [$user->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_chat_list_keeps_pagination_metadata(): void
    {
        $this->actingAs($this->createUser(), 'api')
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_chat_and_message_http_lifecycle_keeps_membership_and_text(): void
    {
        $creator = $this->createUser();
        $member = $this->createUser();
        $this->actingAs($creator, 'api');

        $chatId = $this->postJson('/api/chats', [
            'type' => ChatTypeEnum::GROUP->value, 'name' => 'Project room',
            'user_ids' => [$member->id],
        ])->assertCreated()->assertJsonPath('data.name', 'Project room')->json('data.id');
        $this->getJson('/api/chats/'.$chatId)->assertOk()->assertJsonPath('data.id', $chatId);
        $this->getJson('/api/chats/'.$chatId.'/members')->assertOk();

        $messageId = $this->postJson('/api/chats/'.$chatId.'/messages', ['text' => 'First update'])
            ->assertOk()->assertJsonPath('data.text', 'First update')->json('data.id');
        $this->getJson('/api/chats/'.$chatId.'/messages')->assertOk();
        $this->putJson('/api/chats/messages/'.$messageId, ['text' => 'Edited update'])
            ->assertOk()->assertJsonPath('data.text', 'Edited update');
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'text' => 'Edited update']);

        $this->putJson('/api/chats/'.$chatId, ['name' => 'Renamed room'])
            ->assertOk()->assertJsonPath('data.name', 'Renamed room');
        $this->deleteJson('/api/chats/messages/'.$messageId)->assertOk();
        $this->assertSoftDeleted('chat_messages', ['id' => $messageId]);
        $this->deleteJson('/api/chats/'.$chatId)->assertOk();
    }

    public function test_non_member_cannot_send_or_edit_message_in_another_chat(): void
    {
        $creator = $this->createUser();
        $member = $this->createUser();
        $stranger = $this->createUser();
        $this->actingAs($creator, 'api');
        $chatId = $this->postJson('/api/chats', [
            'type' => ChatTypeEnum::GROUP->value, 'name' => 'Private room',
            'user_ids' => [$member->id],
        ])->assertCreated()->json('data.id');
        $messageId = $this->postJson('/api/chats/'.$chatId.'/messages', ['text' => 'Private'])
            ->assertOk()->json('data.id');

        $this->actingAs($stranger, 'api')
            ->postJson('/api/chats/'.$chatId.'/messages', ['text' => 'Intrusion'])
            ->assertStatus(400);
        $this->putJson('/api/chats/messages/'.$messageId, ['text' => 'Tampered'])
            ->assertNotFound();

        $this->assertSame(1, DB::table('chat_messages')->where('chat_id', $chatId)->count());
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'text' => 'Private']);
    }

    public function test_group_creator_controls_membership_through_api(): void
    {
        $creator = $this->createUser();
        $initialMember = $this->createUser();
        $newMember = $this->createUser();
        $this->actingAs($creator, 'api');
        $chatId = $this->postJson('/api/chats', [
            'type' => ChatTypeEnum::GROUP->value, 'name' => 'Team',
            'user_ids' => [$initialMember->id],
        ])->assertCreated()->json('data.id');

        $this->actingAs($initialMember, 'api')->postJson('/api/chats/'.$chatId.'/members', [
            'user_ids' => [$newMember->id],
        ])->assertStatus(400);
        $this->assertDatabaseMissing('chat_members', ['chat_id' => $chatId, 'user_id' => $newMember->id]);

        $this->actingAs($creator, 'api')->postJson('/api/chats/'.$chatId.'/members', [
            'user_ids' => [$newMember->id],
        ])->assertOk();
        $memberId = ChatMember::where('chat_id', $chatId)->where('user_id', $newMember->id)->firstOrFail()->id;
        $this->getJson('/api/chats/'.$chatId.'/members')->assertOk();

        $this->actingAs($initialMember, 'api')->deleteJson('/api/chats/members/'.$memberId)
            ->assertStatus(400);
        $this->assertDatabaseHas('chat_members', ['id' => $memberId]);
        $this->actingAs($creator, 'api')->deleteJson('/api/chats/members/'.$memberId)->assertOk();
        $this->assertDatabaseMissing('chat_members', ['id' => $memberId]);
    }

    private function createUser(): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]));
    }
}
