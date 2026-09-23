<?php

namespace App\Console\Commands;

use App\Domains\Billing\PendingBankPoller;
use Illuminate\Console\Command;

class PollBillingBankTransfers extends Command
{
    protected $signature = 'billing:poll-bank
                            {--transactions= : JSON transactions for tests}
                            {--force : Bypass histbank throttle and backoff}';

    protected $description = 'Match pending VietQR orders against incoming bank credits.';

    public function handle(PendingBankPoller $poller): int
    {
        $raw = $this->option('transactions');
        $rows = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $matched = $poller->run(is_array($rows) ? $rows : null, (bool) $this->option('force'));
        $this->info("matched={$matched}");

        return self::SUCCESS;
    }
}
