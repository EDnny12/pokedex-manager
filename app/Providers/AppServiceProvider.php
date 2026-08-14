<?php

namespace App\Providers;

use App\Contracts\AssistantAgent;
use App\Contracts\PokemonCatalog;
use App\Services\Assistant\AiAgentClient;
use App\Services\PokeApi\PokeApiCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PokemonCatalog::class, PokeApiCatalog::class);
        $this->app->bind(AssistantAgent::class, AiAgentClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('assistant-read', fn (Request $request): Limit => Limit::perMinute(90)
            ->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('assistant-write', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('assistant-chat', fn (Request $request): Limit => Limit::perMinute(12)
            ->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('assistant-internal', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->attributes->get('assistant_user')?->getAuthIdentifier() ?? $request->ip())));
    }
}
