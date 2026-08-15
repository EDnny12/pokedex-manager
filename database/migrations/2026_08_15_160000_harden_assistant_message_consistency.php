<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assistant_messages', function (Blueprint $table): void {
            $table->uuid('reply_to_message_id')->nullable();
        });

        DB::statement(
            'ALTER TABLE assistant_messages ADD CONSTRAINT assistant_messages_reply_to_message_fk '
            .'FOREIGN KEY (reply_to_message_id) REFERENCES assistant_messages (id) ON DELETE CASCADE NOT VALID',
        );
        DB::statement(
            'ALTER TABLE assistant_messages VALIDATE CONSTRAINT assistant_messages_reply_to_message_fk',
        );
        DB::statement(
            'ALTER TABLE assistant_messages ADD CONSTRAINT assistant_messages_reply_role_valid '
            ."CHECK (reply_to_message_id IS NULL OR role = 'assistant') NOT VALID",
        );
        DB::statement(
            'ALTER TABLE assistant_messages VALIDATE CONSTRAINT assistant_messages_reply_role_valid',
        );
        DB::statement(
            'CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS assistant_messages_reply_to_message_unique_index '
            .'ON assistant_messages (reply_to_message_id) WHERE reply_to_message_id IS NOT NULL',
        );

        foreach (['assistant_conversations', 'assistant_messages', 'assistant_actions', 'assistant_message_attachments'] as $table) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN created_at SET NOT NULL");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN updated_at SET NOT NULL");
        }

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS assistant_actions_confirmed_updated_at_index '
            .'ON assistant_actions (updated_at, id) WHERE status = \'confirmed\'',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_actions_confirmed_updated_at_index');

        foreach (['assistant_conversations', 'assistant_messages', 'assistant_actions', 'assistant_message_attachments'] as $table) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN created_at DROP NOT NULL");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN updated_at DROP NOT NULL");
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assistant_messages_reply_to_message_unique_index');
        DB::statement('ALTER TABLE assistant_messages DROP CONSTRAINT IF EXISTS assistant_messages_reply_role_valid');
        DB::statement('ALTER TABLE assistant_messages DROP CONSTRAINT IF EXISTS assistant_messages_reply_to_message_fk');

        Schema::table('assistant_messages', function (Blueprint $table): void {
            $table->dropColumn('reply_to_message_id');
        });
    }
};
