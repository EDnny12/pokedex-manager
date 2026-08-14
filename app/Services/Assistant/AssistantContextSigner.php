<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class AssistantContextSigner
{
    public function for(User $user, AssistantConversation $conversation): string
    {
        $payload = $this->encode(json_encode([
            'sub' => $user->getKey(),
            'conversation_id' => $conversation->getKey(),
            'exp' => now()->addMinutes(5)->getTimestamp(),
            'nonce' => Str::uuid()->toString(),
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->signature($payload);
    }

    /** @return array{sub: int, conversation_id: string, exp: int, nonce: string} */
    public function verify(string $token): array
    {
        [$payload, $providedSignature] = array_pad(explode('.', $token, 2), 2, null);

        if (! is_string($payload)
            || ! is_string($providedSignature)
            || ! hash_equals($this->signature($payload), $providedSignature)) {
            throw new RuntimeException('El contexto del asistente no es válido.');
        }

        $decoded = json_decode($this->decode($payload), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)
            || ! is_numeric(Arr::get($decoded, 'sub'))
            || ! is_string(Arr::get($decoded, 'conversation_id'))
            || ! is_numeric(Arr::get($decoded, 'exp'))
            || (int) Arr::get($decoded, 'exp') < now()->getTimestamp()) {
            throw new RuntimeException('El contexto del asistente expiró.');
        }

        return [
            'sub' => (int) Arr::get($decoded, 'sub'),
            'conversation_id' => (string) Arr::get($decoded, 'conversation_id'),
            'exp' => (int) Arr::get($decoded, 'exp'),
            'nonce' => (string) Arr::get($decoded, 'nonce', ''),
        ];
    }

    private function signature(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', $payload, $this->secret(), true));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('El contexto del asistente no es válido.');
        }

        return $decoded;
    }

    private function secret(): string
    {
        $secret = (string) config('services.assistant.context_secret');

        if ($secret === '') {
            throw new RuntimeException('El secreto de contexto del asistente no está configurado.');
        }

        return $secret;
    }
}
