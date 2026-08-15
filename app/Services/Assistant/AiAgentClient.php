<?php

namespace App\Services\Assistant;

use App\Contracts\AssistantAgent;
use App\Exceptions\AssistantUserException;
use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AiAgentClient implements AssistantAgent
{
    public function __construct(private AssistantContextSigner $contextSigner) {}

    public function respond(
        User $user,
        AssistantConversation $conversation,
        array $history,
        string $message,
        array $attachments,
        string $requestId,
    ): array {
        try {
            $response = $this->client($requestId)->post('/chat', [
                'conversationId' => $conversation->getKey(),
                'contextToken' => $this->contextSigner->for($user, $conversation),
                'history' => $history,
                'message' => $message,
                'attachments' => $attachments,
                'requestId' => $requestId,
            ])->throw();
        } catch (ConnectionException $exception) {
            throw new AssistantUserException('Pika IA no está disponible en este momento.', previous: $exception);
        }

        $content = $response->json('content');

        if (! is_string($content) || $content === '') {
            throw new AssistantUserException('Pika IA devolvió una respuesta no válida.');
        }

        return [
            'content' => $content,
            'metadata' => [
                'request_id' => $requestId,
                'model' => $response->json('model'),
                'tools' => $response->json('tools', []),
                'duration_ms' => $response->json('durationMs'),
            ],
        ];
    }

    public function execute(User $user, AssistantAction $action, string $requestId): array
    {
        try {
            return $this->client($requestId, shouldRetry: true)
                ->post('/actions/execute', [
                    'actionId' => $action->getKey(),
                    'contextToken' => $this->contextSigner->for($user, $action->conversation),
                    'requestId' => $requestId,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException $exception) {
            throw new AssistantUserException('No pudimos ejecutar la acción en este momento.', previous: $exception);
        }
    }

    private function client(string $requestId, bool $shouldRetry = false): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.assistant.agent_url'))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('services.assistant.service_secret'))
            ->withHeader('X-Request-Id', $requestId)
            ->connectTimeout((int) config('services.assistant.connect_timeout', 3))
            ->timeout((int) config('services.assistant.timeout', 90));

        return $shouldRetry
            ? $request->retry([200, 500], throw: false)
            : $request;
    }
}
