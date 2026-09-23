<?php

namespace App\Console\Commands;

use App\Domains\Billing\HotelPlan;
use App\Models\BillingOrder;
use App\Models\Hotel;
use Illuminate\Console\Command;

class ExpireHotelSubscriptions extends Command
{
    protected $signature = 'billing:expire-hotels';

    protected $description = 'Downgrade expired paid hotels back to the free 1-TV plan.';

    public function handle(): int
    {
        $expired = Hotel::query()
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<=', now())
            ->whereIn('plan', [HotelPlan::STANDARD, HotelPlan::PREMIUM])
            ->get();

        foreach ($expired as $hotel) {
            $hotel->forceFill([
                'plan' => HotelPlan::FREE,
                'device_limit' => HotelPlan::deviceLimit(HotelPlan::FREE),
            ])->save();
        }

        BillingOrder::query()
            ->where('status', BillingOrder::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => BillingOrder::EXPIRED]);

        $this->info('hotels='.$expired->count());

        return self::SUCCESS;
    }
}
