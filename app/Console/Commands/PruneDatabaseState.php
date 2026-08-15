<?php

namespace App\Console\Commands;

use App\Enums\AssistantActionStatus;
use App\Models\AssistantAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

#[Signature('app:prune-database-state {--batch=1000 : Maximum rows deleted per batch}')]
#[Description('Expire stale assistant actions and prune terminal actions and expired database cache rows')]
class PruneDatabaseState extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = max(100, min(10_000, (int) $this->option('batch')));
        $expiredActions = AssistantAction::query()
            ->where('status', AssistantActionStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => AssistantActionStatus::Expired]);
        $confirmedTimeout = max(
            2,
            (int) config('database.monitoring.confirmed_action_timeout_minutes', 5),
        );
        $failedActions = AssistantAction::query()
            ->where('status', AssistantActionStatus::Confirmed)
            ->where('updated_at', '<=', now()->subMinutes($confirmedTimeout))
            ->update([
                'status' => AssistantActionStatus::Failed,
                'failure_message' => 'La ejecución no finalizó dentro del tiempo esperado.',
            ]);
        $deletedActions = $this->pruneTerminalActions($batchSize);
        [$deletedCacheEntries, $deletedCacheLocks] = $this->pruneDatabaseCache($batchSize);

        $this->components->info(
            "Expired {$expiredActions} pending actions; failed {$failedActions} stale confirmed actions; "
            ."deleted {$deletedActions} terminal actions, "
            ."{$deletedCacheEntries} cache entries, and {$deletedCacheLocks} cache locks.",
        );

        return self::SUCCESS;
    }

    private function pruneTerminalActions(int $batchSize): int
    {
        $deleted = 0;
        $retentionDays = max(1, (int) config('database.monitoring.action_retention_days', 30));

        do {
            $actionIds = AssistantAction::query()
                ->whereIn('status', [
                    AssistantActionStatus::Cancelled,
                    AssistantActionStatus::Executed,
                    AssistantActionStatus::Failed,
                    AssistantActionStatus::Expired,
                ])
                ->where('updated_at', '<', now()->subDays($retentionDays))
                ->orderBy('updated_at')
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($actionIds->isEmpty()) {
                break;
            }

            $deleted += AssistantAction::query()->whereKey($actionIds)->delete();
        } while ($actionIds->count() === $batchSize);

        return $deleted;
    }

    /** @return array{int, int} */
    private function pruneDatabaseCache(int $batchSize): array
    {
        $storeName = (string) config('cache.default');
        $store = config("cache.stores.{$storeName}", []);

        if (! is_array($store) || ($store['driver'] ?? null) !== 'database') {
            return [0, 0];
        }

        $connection = DB::connection($store['connection'] ?? null);
        $expiration = now()->getTimestamp();
        $cacheEntries = $this->pruneExpiredRows(
            $connection,
            (string) ($store['table'] ?? 'cache'),
            $expiration,
            $batchSize,
        );
        $cacheLocks = $this->pruneExpiredRows(
            DB::connection($store['lock_connection'] ?? $store['connection'] ?? null),
            (string) ($store['lock_table'] ?? 'cache_locks'),
            $expiration,
            $batchSize,
        );

        return [$cacheEntries, $cacheLocks];
    }

    private function pruneExpiredRows(
        ConnectionInterface $connection,
        string $table,
        int $expiration,
        int $batchSize,
    ): int {
        $deleted = 0;

        do {
            $keys = $connection->table($table)
                ->where('expiration', '<=', $expiration)
                ->orderBy('expiration')
                ->limit($batchSize)
                ->pluck('key');

            if ($keys->isEmpty()) {
                break;
            }

            $deleted += $connection->table($table)
                ->whereIn('key', $keys)
                ->where('expiration', '<=', $expiration)
                ->delete();
        } while ($keys->count() === $batchSize);

        return $deleted;
    }
}
