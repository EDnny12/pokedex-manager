<?php

namespace Tests\Feature;

use App\Contracts\AssistantAgent;
use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Enums\AssistantMessageRole;
use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_list_a_conversation(): void
    {
        $user = User::factory()->create();

        $createResponse = $this->actingAs($user)->postJson(route('assistant.conversations.store'));

        $createResponse->assertCreated()->assertJsonPath('data.title', 'Nueva conversación');
        $conversationId = $createResponse->json('data.id');

        $this->actingAs($user)
            ->getJson(route('assistant.conversations.index', ['conversation' => $conversationId]))
            ->assertOk()
            ->assertJsonPath('active_conversation.id', $conversationId)
            ->assertJsonCount(1, 'conversations');

        $this->actingAs($user)
            ->getJson(route('assistant.conversations.show', $conversationId))
            ->assertOk()
            ->assertJsonPath('active_conversation.id', $conversationId)
            ->assertJsonMissingPath('conversations');
    }

    public function test_history_uses_a_stable_cursor_from_the_latest_messages(): void
    {
        config(['services.assistant.message_page_size' => 20]);
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $start = now()->subMinutes(25);

        foreach (range(0, 24) as $index) {
            AssistantMessage::factory()->for($conversation, 'conversation')->create([
                'content' => "Mensaje {$index}",
                'created_at' => $start->copy()->addSeconds($index),
                'updated_at' => $start->copy()->addSeconds($index),
            ]);
        }

        $firstPage = $this->actingAs($user)
            ->getJson(route('assistant.conversations.index', ['conversation' => $conversation]))
            ->assertOk()
            ->assertJsonCount(20, 'messages.data')
            ->assertJsonPath('messages.data.0.content', 'Mensaje 5')
            ->assertJsonPath('messages.data.19.content', 'Mensaje 24')
            ->assertJsonPath('messages.has_more', true)
            ->assertJsonPath('conversations.0.preview', 'Mensaje 24');
        $cursor = $firstPage->json('messages.next_cursor');

        $this->actingAs($user)
            ->getJson(route('assistant.messages.index', [
                'assistantConversation' => $conversation,
                'cursor' => $cursor,
            ]))
            ->assertOk()
            ->assertJsonCount(5, 'messages.data')
            ->assertJsonPath('messages.data.0.content', 'Mensaje 0')
            ->assertJsonPath('messages.data.4.content', 'Mensaje 4')
            ->assertJsonPath('messages.next_cursor', null)
            ->assertJsonPath('messages.has_more', false);
    }

    public function test_invalid_history_cursor_is_rejected(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson(route('assistant.messages.index', [
                'assistantConversation' => $conversation,
                'cursor' => 'invalid-cursor',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cursor');
    }

    public function test_user_cannot_paginate_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->getJson(route('assistant.messages.index', $conversation))
            ->assertForbidden();
    }

    public function test_conversation_returns_actions_in_history(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $pending = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create();
        $expired = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create(['expires_at' => now()->subMinute(), 'status' => AssistantActionStatus::Expired]);
        $confirmed = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create([
                'status' => AssistantActionStatus::Confirmed,
            ]);
        $executed = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create([
                'status' => AssistantActionStatus::Executed,
                'executed_at' => now(),
            ]);

        $this->actingAs($user)
            ->getJson(route('assistant.conversations.index', ['conversation' => $conversation]))
            ->assertOk()
            ->assertJsonCount(4, 'actions')
            ->assertJsonPath('actions.0.id', $pending->getKey())
            ->assertJsonPath('actions.1.id', $expired->getKey())
            ->assertJsonPath('actions.2.id', $confirmed->getKey())
            ->assertJsonPath('actions.3.id', $executed->getKey());
    }

    public function test_message_is_persisted_with_agent_response(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create([
            'title' => 'Nueva conversación',
            'last_message_at' => null,
        ]);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('respond')->once()->andReturn([
                'content' => 'Tienes 3 Pokémon de tipo eléctrico.',
                'metadata' => ['model' => 'gemini-3.5-flash-lite', 'tools' => ['get_my_collection']],
            ]);
        });

        $response = $this->actingAs($user)->postJson(route('assistant.messages.store', $conversation), [
            'message' => '¿Cuántos eléctricos tengo?',
            'client_message_id' => fake()->uuid(),
        ]);

        $response->assertOk()
            ->assertJsonPath('assistant_message.content', 'Tienes 3 Pokémon de tipo eléctrico.')
            ->assertJsonPath('assistant_message.metadata.model', 'gemini-3.5-flash-lite');
        $this->assertSame(2, AssistantMessage::query()->count());
        $this->assertSame('¿Cuántos eléctricos tengo?', $conversation->messages()->oldest()->value('content'));
        $this->assertNotSame('Nueva conversación', $conversation->fresh()->title);
    }

    public function test_repeated_client_message_id_returns_the_persisted_response_once(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $clientMessageId = fake()->uuid();

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('respond')->once()->andReturn([
                'content' => 'Respuesta idempotente.',
                'metadata' => [],
            ]);
        });

        $payload = [
            'message' => 'Háblame de Pikachu.',
            'client_message_id' => $clientMessageId,
        ];

        $this->actingAs($user)
            ->postJson(route('assistant.messages.store', $conversation), $payload)
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'Respuesta idempotente.');

        $this->actingAs($user)
            ->postJson(route('assistant.messages.store', $conversation), $payload)
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'Respuesta idempotente.');

        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame(
            $conversation->messages()->where('role', AssistantMessageRole::Assistant)->value('reply_to_message_id'),
            $conversation->messages()->where('client_message_id', $clientMessageId)->value('id'),
        );
    }

    public function test_repeated_client_message_id_returns_its_response_even_with_same_second_messages(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $clientMessageId = fake()->uuid();
        $createdAt = now()->startOfSecond();
        $userMessage = AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create([
                'role' => AssistantMessageRole::User,
                'client_message_id' => $clientMessageId,
                'metadata' => ['request_id' => fake()->uuid()],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create([
                'role' => AssistantMessageRole::Assistant,
                'content' => 'Respuesta de otro mensaje.',
                'client_message_id' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create([
                'role' => AssistantMessageRole::Assistant,
                'content' => 'Respuesta correcta.',
                'client_message_id' => null,
                'reply_to_message_id' => $userMessage->getKey(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('respond')->never();
        });

        $this->actingAs($user)
            ->postJson(route('assistant.messages.store', $conversation), [
                'message' => 'Mensaje repetido.',
                'client_message_id' => $clientMessageId,
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'Respuesta correcta.');
    }

    public function test_stale_incomplete_message_can_be_retried_without_creating_a_duplicate_user_message(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $clientMessageId = fake()->uuid();
        $createdAt = now()->subMinutes(3);

        AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create([
                'client_message_id' => $clientMessageId,
                'metadata' => ['request_id' => fake()->uuid()],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('respond')->once()->andReturn([
                'content' => 'Respuesta recuperada.',
                'metadata' => [],
            ]);
        });

        $this->actingAs($user)
            ->postJson(route('assistant.messages.store', $conversation), [
                'message' => 'Reintentar este mensaje.',
                'client_message_id' => $clientMessageId,
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'Respuesta recuperada.');

        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_failed_generation_does_not_leave_an_orphan_message_or_retry_the_chat_request(): void
    {
        Storage::fake('local');
        config([
            'services.assistant.agent_url' => 'http://ai-agent.test',
            'services.assistant.service_secret' => 'test-service-secret',
        ]);
        Http::fake([
            'http://ai-agent.test/chat' => Http::response(['message' => 'No disponible.'], 503),
        ]);
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create([
            'title' => 'Nueva conversación',
            'last_message_at' => null,
        ]);

        $this->actingAs($user)->post(route('assistant.messages.store', $conversation), [
            'message' => '¿Qué Pokémon aparece?',
            'client_message_id' => fake()->uuid(),
            'images' => [UploadedFile::fake()->image('pokemon.png')],
        ], ['Accept' => 'application/json'])
            ->assertServiceUnavailable();

        Http::assertSentCount(1);
        $this->assertSame(0, $conversation->messages()->count());
        $this->assertSame(0, AssistantMessageAttachment::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame('Nueva conversación', $conversation->fresh()->title);
    }

    public function test_image_is_stored_privately_and_sent_to_the_agent_as_visual_context(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $image = UploadedFile::fake()->image('pikachu.png', 320, 240);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('respond')
                ->once()
                ->withArgs(function (
                    User $user,
                    AssistantConversation $conversation,
                    array $history,
                    string $message,
                    array $attachments,
                    string $requestId,
                ): bool {
                    $this->assertSame('¿Qué Pokémon aparece?', $message);
                    $this->assertSame([], $history);
                    $this->assertCount(1, $attachments);
                    $this->assertSame('image/png', $attachments[0]['mimeType']);
                    $this->assertNotSame('', base64_decode($attachments[0]['data'], true));

                    return true;
                })
                ->andReturn([
                    'content' => 'Parece Pikachu; verificaré sus datos antes de darte detalles.',
                    'metadata' => ['model' => 'gemini-3.5-flash-lite'],
                ]);
        });

        $response = $this->actingAs($user)->post(route('assistant.messages.store', $conversation), [
            'message' => '¿Qué Pokémon aparece?',
            'client_message_id' => fake()->uuid(),
            'images' => [$image],
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('user_message.attachments.0.name', 'pikachu.png')
            ->assertJsonPath('user_message.attachments.0.mime_type', 'image/png');

        $attachment = AssistantMessageAttachment::query()->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringContainsString(
            route('assistant.attachments.show', $attachment),
            $response->json('user_message.attachments.0.url'),
        );
    }

    public function test_message_can_contain_only_an_image(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('respond')
                ->once()
                ->withArgs(fn (
                    User $user,
                    AssistantConversation $conversation,
                    array $history,
                    string $message,
                    array $attachments,
                ): bool => $message === 'Analiza esta imagen.' && count($attachments) === 1)
                ->andReturn([
                    'content' => 'Veo una imagen para analizar.',
                    'metadata' => [],
                ]);
        });

        $this->actingAs($user)->post(route('assistant.messages.store', $conversation), [
            'client_message_id' => fake()->uuid(),
            'images' => [UploadedFile::fake()->image('pokemon.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('user_message.content', 'Analiza esta imagen.');
    }

    public function test_unexpected_message_errors_return_a_generic_response(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('respond')
                ->once()
                ->andThrow(new QueryException(
                    'pgsql',
                    'select secret from private_table',
                    [],
                    new PDOException('private database details'),
                ));
        });

        $this->actingAs($user)
            ->postJson(route('assistant.messages.store', $conversation), [
                'message' => 'Consulta esto',
                'client_message_id' => fake()->uuid(),
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'No pudimos obtener una respuesta. Inténtalo de nuevo.')
            ->assertJsonMissing(['message' => 'private database details']);
    }

    public function test_persisted_image_is_available_as_context_for_a_follow_up_message(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $previousMessage = AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create(['content' => '¿Qué aparece en esta imagen?']);
        $attachment = AssistantMessageAttachment::factory()
            ->for($previousMessage, 'message')
            ->create(['size' => 13]);
        Storage::disk('local')->put($attachment->path, 'visual-context');
        AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create([
                'role' => AssistantMessageRole::Assistant,
                'content' => 'Veo un Pokémon en la imagen.',
                'client_message_id' => null,
            ]);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('respond')
                ->once()
                ->withArgs(function (
                    User $user,
                    AssistantConversation $conversation,
                    array $history,
                    string $message,
                ): bool {
                    $this->assertSame('¿Y cuál podría ser?', $message);
                    $historicalAttachments = array_merge(...array_column($history, 'attachments'));
                    $this->assertCount(1, $historicalAttachments);
                    $this->assertSame(
                        base64_encode('visual-context'),
                        $historicalAttachments[0]['data'],
                    );

                    return true;
                })
                ->andReturn([
                    'content' => 'Podría ser Pikachu, pero necesito verificarlo.',
                    'metadata' => [],
                ]);
        });

        $this->actingAs($user)->postJson(route('assistant.messages.store', $conversation), [
            'message' => '¿Y cuál podría ser?',
            'client_message_id' => fake()->uuid(),
        ])->assertOk();
    }

    public function test_invalid_or_excessive_images_are_rejected(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->actingAs($user)->post(route('assistant.messages.store', $conversation), [
            'client_message_id' => fake()->uuid(),
            'images' => [UploadedFile::fake()->create('instrucciones.txt', 10, 'text/plain')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images.0');
    }

    public function test_only_the_owner_can_view_a_private_attachment(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($owner)->create();
        $message = AssistantMessage::factory()->for($conversation, 'conversation')->create();
        $attachment = AssistantMessageAttachment::factory()->for($message, 'message')->create();
        Storage::disk('local')->put($attachment->path, 'private-image');

        $this->actingAs($owner)
            ->get(route('assistant.attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($intruder)
            ->get(route('assistant.attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_user_cannot_read_or_write_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->getJson(route('assistant.conversations.index', ['conversation' => $conversation->getKey()]))
            ->assertOk()
            ->assertJsonPath('active_conversation', null);

        $this->actingAs($intruder)
            ->postJson(route('assistant.messages.store', $conversation), [
                'message' => 'Muéstrame datos privados.',
                'client_message_id' => fake()->uuid(),
            ])
            ->assertForbidden();
    }

    public function test_deleting_conversation_cascades_messages_and_actions(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $message = AssistantMessage::factory()->for($conversation, 'conversation')->create();
        $attachment = AssistantMessageAttachment::factory()->for($message, 'message')->create();
        Storage::disk('local')->put($attachment->path, 'private-image');
        $action = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create();

        $this->actingAs($user)
            ->deleteJson(route('assistant.conversations.destroy', $conversation))
            ->assertOk();

        $this->assertModelMissing($conversation);
        $this->assertModelMissing($message);
        $this->assertModelMissing($attachment);
        $this->assertModelMissing($action);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_confirmation_executes_only_the_owned_pending_action(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $action = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create([
                'type' => AssistantActionType::AddPokemon,
                'status' => AssistantActionStatus::Pending,
            ]);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andReturnUsing(function (User $user, AssistantAction $action): array {
                $action->update([
                    'status' => AssistantActionStatus::Executed,
                    'executed_at' => now(),
                ]);

                return ['status' => 'executed'];
            });
        });

        $this->actingAs($user)
            ->postJson(route('assistant.actions.confirm', $action))
            ->assertOk()
            ->assertJsonPath('action.status', 'executed');

        $this->assertSame(AssistantActionStatus::Executed, $action->fresh()->status);
    }

    public function test_ambiguous_agent_failure_does_not_downgrade_an_executed_action(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $action = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create([
                'type' => AssistantActionType::AddPokemon,
                'status' => AssistantActionStatus::Pending,
            ]);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andReturnUsing(function (User $user, AssistantAction $action): array {
                $action->update([
                    'status' => AssistantActionStatus::Executed,
                    'executed_at' => now(),
                ]);

                throw new RuntimeException('La respuesta se perdió después de ejecutar la acción.');
            });
        });

        $this->actingAs($user)
            ->postJson(route('assistant.actions.confirm', $action))
            ->assertStatus(503);

        $this->assertSame(AssistantActionStatus::Executed, $action->fresh()->status);
        $this->assertNull($action->fresh()->failure_message);
    }

    public function test_unexpected_action_errors_return_a_generic_response(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $action = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create();

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new QueryException(
                    'pgsql',
                    'select secret from private_table',
                    [],
                    new PDOException('private database details'),
                ));
        });

        $this->actingAs($user)
            ->postJson(route('assistant.actions.confirm', $action))
            ->assertStatus(503)
            ->assertJsonPath('message', 'No pudimos completar la acción. Inténtalo de nuevo.')
            ->assertJsonMissing(['message' => 'private database details']);
    }

    public function test_user_cannot_confirm_another_users_action(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($owner)->create();
        $action = AssistantAction::factory()
            ->for($owner)
            ->for($conversation, 'conversation')
            ->create();

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('execute');
        });

        $this->actingAs($intruder)
            ->postJson(route('assistant.actions.confirm', $action))
            ->assertForbidden();

        $this->assertSame(AssistantActionStatus::Pending, $action->fresh()->status);
    }

    public function test_expired_action_cannot_be_confirmed(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $action = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create([
                'status' => AssistantActionStatus::Pending,
                'expires_at' => now()->subMinute(),
            ]);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('execute');
        });

        $this->actingAs($user)
            ->postJson(route('assistant.actions.confirm', $action))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La confirmación expiró. Solicita la acción nuevamente.');

        $this->assertSame(AssistantActionStatus::Expired, $action->fresh()->status);
    }

    public function test_cancelled_action_cannot_be_confirmed_or_cancelled_again(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $action = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create(['status' => AssistantActionStatus::Pending]);

        $this->mock(AssistantAgent::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('execute');
        });

        $this->actingAs($user)
            ->postJson(route('assistant.actions.cancel', $action))
            ->assertOk()
            ->assertJsonPath('action.status', 'cancelled');

        $this->actingAs($user)
            ->postJson(route('assistant.actions.cancel', $action))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta acción ya no se puede cancelar.');

        $this->actingAs($user)
            ->postJson(route('assistant.actions.confirm', $action))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta acción ya no se puede confirmar.');
    }
}
