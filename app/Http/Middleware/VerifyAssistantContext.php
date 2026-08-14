<?php

namespace App\Http\Middleware;

use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\AssistantContextSigner;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VerifyAssistantContext
{
    public function __construct(private AssistantContextSigner $signer) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $context = $this->signer->verify((string) $request->bearerToken());
        } catch (RuntimeException) {
            return response()->json(['message' => 'El contexto del asistente no es válido o expiró.'], 401);
        }

        $user = User::query()->find($context['sub']);
        $conversation = AssistantConversation::query()->find($context['conversation_id']);

        if (! $user instanceof User
            || ! $conversation instanceof AssistantConversation
            || $conversation->user_id !== $user->getKey()) {
            return response()->json(['message' => 'El contexto no tiene acceso a esta conversación.'], 403);
        }

        $request->attributes->set('assistant_user', $user);
        $request->attributes->set('assistant_conversation', $conversation);
        $request->attributes->set('assistant_context', $context);

        return $next($request);
    }
}
