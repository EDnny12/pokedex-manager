<?php

namespace App\Http\Controllers;

use App\Services\TrainerCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Agent;
use Laravel\Jetstream\Http\Controllers\Inertia\Concerns\ConfirmsTwoFactorAuthentication;

class UserProfileController extends Controller
{
    use ConfirmsTwoFactorAuthentication;

    public function __construct(private TrainerCardService $trainerCardService) {}

    public function show(Request $request): Response
    {
        $this->validateTwoFactorAuthenticationState($request);

        $user = $request->user();
        $trainerCard = $this->trainerCardService->forUser($user);

        return Inertia::render('Profile/Show', [
            'trainerCard' => $trainerCard,
            'confirmsTwoFactorAuthentication' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'sessions' => $this->sessions($request)->all(),
        ]);
    }

    public function regenerateBio(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $card = $this->trainerCardService->forUser($user, forceRefreshBio: true);

        if ($request->wantsJson()) {
            return response()->json([
                'headline' => $card['headline'],
                'description' => $card['description'],
                'is_ai_generated' => $card['is_ai_generated'],
            ]);
        }

        return back(303)->with('flash', [
            'type' => 'success',
            'message' => 'Identidad de entrenador actualizada con Pika IA.',
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    protected function sessions(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->orderBy('last_activity', 'desc')
                ->get()
        )->map(function ($session) use ($request) {
            $agent = $this->createAgent($session);

            return (object) [
                'agent' => [
                    'is_desktop' => $agent->isDesktop(),
                    'platform' => $agent->platform(),
                    'browser' => $agent->browser(),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        });
    }

    /**
     * @param  mixed  $session
     */
    protected function createAgent($session): Agent
    {
        return tap(new Agent, fn ($agent) => $agent->setUserAgent($session->user_agent));
    }
}
