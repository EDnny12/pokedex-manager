<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $constraints = [
            'ALTER TABLE pokemon_collection_items ADD CONSTRAINT pokemon_collection_items_pokemon_id_positive CHECK (pokemon_id > 0) NOT VALID',
            "ALTER TABLE assistant_messages ADD CONSTRAINT assistant_messages_role_valid CHECK (role IN ('user', 'assistant')) NOT VALID",
            "ALTER TABLE assistant_messages ADD CONSTRAINT assistant_messages_metadata_json CHECK (metadata IS NULL OR jsonb_typeof(metadata) IN ('object', 'array')) NOT VALID",
            "ALTER TABLE assistant_actions ADD CONSTRAINT assistant_actions_type_valid CHECK (type IN ('add_pokemon', 'remove_pokemon', 'update_pokemon')) NOT VALID",
            "ALTER TABLE assistant_actions ADD CONSTRAINT assistant_actions_status_valid CHECK (status IN ('pending', 'confirmed', 'cancelled', 'executed', 'failed', 'expired')) NOT VALID",
            "ALTER TABLE assistant_actions ADD CONSTRAINT assistant_actions_payload_object CHECK (jsonb_typeof(payload) = 'object') NOT VALID",
            "ALTER TABLE assistant_actions ADD CONSTRAINT assistant_actions_execution_timestamp_valid CHECK (status <> 'executed' OR executed_at IS NOT NULL) NOT VALID",
            'ALTER TABLE assistant_actions ADD CONSTRAINT assistant_actions_user_conversation_fk FOREIGN KEY (conversation_id, user_id) REFERENCES assistant_conversations (id, user_id) ON DELETE CASCADE NOT VALID',
            'ALTER TABLE assistant_message_attachments ADD CONSTRAINT assistant_attachments_size_nonnegative CHECK (size >= 0) NOT VALID',
            'ALTER TABLE assistant_message_attachments ADD CONSTRAINT assistant_attachments_dimensions_positive CHECK ((width IS NULL OR width > 0) AND (height IS NULL OR height > 0)) NOT VALID',
            "ALTER TABLE assistant_message_attachments ADD CONSTRAINT assistant_attachments_mime_type_valid CHECK (mime_type IN ('image/jpeg', 'image/png', 'image/webp')) NOT VALID",
        ];

        foreach ($constraints as $constraint) {
            DB::statement($constraint);
        }

        $validations = [
            'ALTER TABLE pokemon_collection_items VALIDATE CONSTRAINT pokemon_collection_items_pokemon_id_positive',
            'ALTER TABLE assistant_messages VALIDATE CONSTRAINT assistant_messages_role_valid',
            'ALTER TABLE assistant_messages VALIDATE CONSTRAINT assistant_messages_metadata_json',
            'ALTER TABLE assistant_actions VALIDATE CONSTRAINT assistant_actions_type_valid',
            'ALTER TABLE assistant_actions VALIDATE CONSTRAINT assistant_actions_status_valid',
            'ALTER TABLE assistant_actions VALIDATE CONSTRAINT assistant_actions_payload_object',
            'ALTER TABLE assistant_actions VALIDATE CONSTRAINT assistant_actions_execution_timestamp_valid',
            'ALTER TABLE assistant_actions VALIDATE CONSTRAINT assistant_actions_user_conversation_fk',
            'ALTER TABLE assistant_message_attachments VALIDATE CONSTRAINT assistant_attachments_size_nonnegative',
            'ALTER TABLE assistant_message_attachments VALIDATE CONSTRAINT assistant_attachments_dimensions_positive',
            'ALTER TABLE assistant_message_attachments VALIDATE CONSTRAINT assistant_attachments_mime_type_valid',
        ];

        foreach ($validations as $validation) {
            DB::statement($validation);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE pokemon_collection_items DROP CONSTRAINT IF EXISTS pokemon_collection_items_pokemon_id_positive');
        DB::statement('ALTER TABLE assistant_messages DROP CONSTRAINT IF EXISTS assistant_messages_role_valid');
        DB::statement('ALTER TABLE assistant_messages DROP CONSTRAINT IF EXISTS assistant_messages_metadata_json');
        DB::statement('ALTER TABLE assistant_actions DROP CONSTRAINT IF EXISTS assistant_actions_type_valid');
        DB::statement('ALTER TABLE assistant_actions DROP CONSTRAINT IF EXISTS assistant_actions_status_valid');
        DB::statement('ALTER TABLE assistant_actions DROP CONSTRAINT IF EXISTS assistant_actions_payload_object');
        DB::statement('ALTER TABLE assistant_actions DROP CONSTRAINT IF EXISTS assistant_actions_execution_timestamp_valid');
        DB::statement('ALTER TABLE assistant_actions DROP CONSTRAINT IF EXISTS assistant_actions_user_conversation_fk');
        DB::statement('ALTER TABLE assistant_message_attachments DROP CONSTRAINT IF EXISTS assistant_attachments_size_nonnegative');
        DB::statement('ALTER TABLE assistant_message_attachments DROP CONSTRAINT IF EXISTS assistant_attachments_dimensions_positive');
        DB::statement('ALTER TABLE assistant_message_attachments DROP CONSTRAINT IF EXISTS assistant_attachments_mime_type_valid');
    }
};
