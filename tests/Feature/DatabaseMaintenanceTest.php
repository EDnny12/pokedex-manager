<?php

namespace Tests\Feature;

use App\Enums\AssistantActionStatus;
use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_actions_and_prunes_terminal_actions_and_database_cache(): void
    {
        config([
            'cache.default' => 'database',
            'database.monitoring.action_retention_days' => 30,
            'database.monitoring.confirmed_action_timeout_minutes' => 5,
        ]);
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $expiredAction = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create(['expires_at' => now()->subMinute()]);
        $oldTerminalAction = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create([
                'status' => AssistantActionStatus::Cancelled,
                'updated_at' => now()->subDays(31),
            ]);
        $staleConfirmedAction = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create([
                'status' => AssistantActionStatus::Confirmed,
                'expires_at' => now()->addMinutes(10),
                'updated_at' => now()->subMinutes(6),
            ]);
        DB::table('cache')->insert([
            'key' => 'expired-cache-key',
            'value' => serialize('expired'),
            'expiration' => now()->subMinute()->getTimestamp(),
        ]);
        DB::table('cache_locks')->insert([
            'key' => 'expired-lock-key',
            'owner' => 'test-owner',
            'expiration' => now()->subMinute()->getTimestamp(),
        ]);
        DB::table('cache')->insert([
            'key' => 'active-cache-key',
            'value' => serialize('active'),
            'expiration' => now()->addMinute()->getTimestamp(),
        ]);
        DB::table('cache_locks')->insert([
            'key' => 'active-lock-key',
            'owner' => 'active-owner',
            'expiration' => now()->addMinute()->getTimestamp(),
        ]);

        $this->artisan('app:prune-database-state', ['--batch' => 100])
            ->assertSuccessful();

        $this->assertSame(AssistantActionStatus::Expired, $expiredAction->fresh()->status);
        $this->assertSame(AssistantActionStatus::Failed, $staleConfirmedAction->fresh()->status);
        $this->assertModelMissing($oldTerminalAction);
        $this->assertDatabaseMissing('cache', ['key' => 'expired-cache-key']);
        $this->assertDatabaseMissing('cache_locks', ['key' => 'expired-lock-key']);
        $this->assertDatabaseHas('cache', ['key' => 'active-cache-key']);
        $this->assertDatabaseHas('cache_locks', ['key' => 'active-lock-key']);
    }
}
