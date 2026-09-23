<?php

namespace App\Domains\Billing;

use App\Models\BillingOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PendingBankPoller
{
    public const LAST_POLL_KEY = 'histbank:last_poll_at';

    public const BACKOFF_UNTIL_KEY = 'histbank:backoff_until';

    public const BACKOFF_STEP_KEY = 'histbank:backoff_step';

    public function __construct(
        private BankTransferMatcher $matcher,
        private BillingOrderService $orders,
    ) {}

    /**
     * @param  list<array<string, mixed>>|null  $transactions
     */
    public function run(?array $transactions = null, bool $force = false): int
    {
        $pending = BillingOrder::query()
            ->where('method', 'bank')
            ->where('status', BillingOrder::PENDING)
            ->where('created_at', '>=', now()->subHours(6))
            ->orderBy('created_at')
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        foreach ($pending as $order) {
            $this->orders->expireIfNeeded($order, false);
        }
        $pending = $pending->filter(fn (BillingOrder $order) => $order->status === BillingOrder::PENDING)->values();
        if ($pending->isEmpty()) {
            return 0;
        }

        if (is_array($transactions)) {
            return $this->applyMatches($transactions, $pending);
        }

        if (! $force && $this->shouldSkip($pending)) {
            return 0;
        }

        return $this->applyMatches($this->fetch(), $pending);
    }

    /**
     * @param  Collection<int, BillingOrder>  $pending
     */
    private function shouldSkip(Collection $pending): bool
    {
        $oldestAge = (int) Carbon::parse($pending->min('created_at'))->diffInSeconds(now(), true);
        $initial = (int) config('services.histbank.initial_delay_seconds', 30);
        if ($oldestAge < $initial) {
            return true;
        }

        $backoffUntil = Cache::get(self::BACKOFF_UNTIL_KEY);
        if (is_string($backoffUntil) && $backoffUntil !== '' && now()->lt(Carbon::parse($backoffUntil))) {
            return true;
        }

        $last = Cache::get(self::LAST_POLL_KEY);
        if (is_string($last) && $last !== '' && Carbon::parse($last)->diffInSeconds(now(), true) < $this->intervalSeconds($oldestAge)) {
            return true;
        }

        return false;
    }

    private function intervalSeconds(int $oldestAge): int
    {
        $min = max(60, (int) config('services.histbank.min_interval_seconds', 60));
        if ($oldestAge < 180) {
            return $min;
        }
        if ($oldestAge < 600) {
            return max($min, 90);
        }

        return max($min, 120);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<int, BillingOrder>  $pending
     */
    private function applyMatches(array $rows, Collection $pending): int
    {
        $matched = 0;
        foreach ($this->matcher->match($rows, $pending) as $hit) {
            $this->orders->markPaid($hit['order'], 'bank', $hit['txId'], $hit['description']);
            $matched++;
        }

        return $matched;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetch(): array
    {
        $url = rtrim((string) config('services.histbank.url'), '/');
        if ($url === '') {
            return [];
        }

        try {
            $res = Http::timeout(12)->get($url.'/transactions');
            Cache::put(self::LAST_POLL_KEY, now()->toIso8601String(), 3600);

            if (! $res->successful()) {
                $this->recordBackoff($res->status());
                Log::warning('histbank.fetch_failed', ['status' => $res->status()]);

                return [];
            }

            Cache::forget(self::BACKOFF_UNTIL_KEY);
            Cache::forget(self::BACKOFF_STEP_KEY);

            $json = $res->json();
            $list = $json['transactionInfos'] ?? $json['transactions'] ?? $json;

            return is_array($list) ? $list : [];
        } catch (\Throwable $e) {
            Cache::put(self::LAST_POLL_KEY, now()->toIso8601String(), 3600);
            $this->recordBackoff(0);
            Log::warning('histbank.fetch_error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function recordBackoff(int $status): void
    {
        if (! in_array($status, [0, 401, 423, 429], true) && $status < 500) {
            return;
        }

        $step = (int) Cache::get(self::BACKOFF_STEP_KEY, 0);
        $minutes = [2, 5, 15][min($step, 2)];
        Cache::put(self::BACKOFF_STEP_KEY, $step + 1, 3600);
        Cache::put(self::BACKOFF_UNTIL_KEY, now()->addMinutes($minutes)->toIso8601String(), 3600);
    }
}
