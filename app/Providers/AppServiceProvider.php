<?php

namespace App\Providers;

use App\Contracts\AssistantAgent;
use App\Contracts\PokemonCatalog;
use App\Services\Assistant\AiAgentClient;
use App\Services\PokeApi\PokeApiCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\DatabaseBusy;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
        $this->configurePostgresSession();
        $this->configureDatabaseMonitoring();

        RateLimiter::for('assistant-read', fn (Request $request): Limit => Limit::perMinute(90)
            ->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('assistant-write', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('assistant-chat', fn (Request $request): Limit => Limit::perMinute(12)
            ->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('assistant-internal', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->attributes->get('assistant_user')?->getAuthIdentifier() ?? $request->ip())));
    }

    private function configurePostgresSession(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if (
                $event->connection->getDriverName() !== 'pgsql'
                || $this->app->runningConsoleCommand([
                    'db:wipe',
                    'migrate',
                    'migrate:fresh',
                    'migrate:refresh',
                    'migrate:reset',
                    'migrate:rollback',
                ])
            ) {
                return;
            }

            $timeouts = [
                'statement_timeout' => (int) config('database.monitoring.statement_timeout_ms', 15000),
                'lock_timeout' => (int) config('database.monitoring.lock_timeout_ms', 3000),
                'idle_in_transaction_session_timeout' => (int) config(
                    'database.monitoring.idle_transaction_timeout_ms',
                    10000,
                ),
            ];

            foreach ($timeouts as $setting => $milliseconds) {
                if ($milliseconds > 0) {
                    $event->connection->unprepared("SET {$setting} TO '{$milliseconds}ms'");
                }
            }
        });
    }

    private function configureDatabaseMonitoring(): void
    {
        $threshold = (int) config('database.monitoring.slow_request_threshold_ms', 500);

        if ($threshold > 0) {
            DB::whenQueryingForLongerThan(
                $threshold,
                function (Connection $connection, QueryExecuted $event): void {
                    Log::warning('Database query time exceeded the request threshold.', [
                        'connection' => $connection->getName(),
                        'last_query_time_ms' => $event->time,
                        'total_query_time_ms' => $connection->totalQueryDuration(),
                    ]);
                },
            );
        }

        Event::listen(DatabaseBusy::class, function (DatabaseBusy $event): void {
            Log::warning('Database connection count exceeded the configured threshold.', [
                'connection' => $event->connectionName,
                'connections' => $event->connections,
            ]);
        });
    }
}
