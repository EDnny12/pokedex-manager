<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS assistant_messages_conversation_created_id_index ON assistant_messages (conversation_id, created_at DESC, id DESC)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS assistant_conversations_user_last_message_index ON assistant_conversations (user_id, last_message_at DESC NULLS LAST, created_at DESC, id DESC)');
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS assistant_actions_active_conversation_created_index ON assistant_actions (conversation_id, created_at DESC, id DESC) WHERE status IN ('pending', 'confirmed')");
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS assistant_actions_active_expires_at_index ON assistant_actions (expires_at) WHERE status IN ('pending', 'confirmed')");
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS assistant_actions_terminal_updated_at_index ON assistant_actions (updated_at, id) WHERE status IN ('cancelled', 'executed', 'failed', 'expired')");
        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS assistant_conversations_id_user_unique_index ON assistant_conversations (id, user_id)');

        DB::statement('ALTER TABLE assistant_conversations ADD CONSTRAINT assistant_conversations_id_user_unique UNIQUE USING INDEX assistant_conversations_id_user_unique_index');

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_messages_conversation_id_created_at_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_conversations_user_id_last_message_at_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS assistant_messages_conversation_id_created_at_index ON assistant_messages (conversation_id, created_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS assistant_conversations_user_id_last_message_at_index ON assistant_conversations (user_id, last_message_at)');

        DB::statement('ALTER TABLE assistant_conversations DROP CONSTRAINT IF EXISTS assistant_conversations_id_user_unique');

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_messages_conversation_created_id_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_conversations_user_last_message_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_actions_active_conversation_created_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_actions_active_expires_at_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_actions_terminal_updated_at_index');
    }
};
