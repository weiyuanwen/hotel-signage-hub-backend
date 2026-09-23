<?php

namespace App\Domains\Billing;

class VietQrService
{
    public function imageUrl(string $orderCode, int $amount): string
    {
        $bank = (string) config('services.vietqr.bank_id');
        $account = (string) config('services.vietqr.account_no');
        $name = (string) config('services.vietqr.account_name');
        $template = (string) config('services.vietqr.template', 'compact2');

        $query = http_build_query([
            'amount' => $amount,
            'addInfo' => $orderCode,
            'accountName' => $name,
        ]);

        return "https://img.vietqr.io/image/{$bank}-{$account}-{$template}.png?{$query}";
    }
}
