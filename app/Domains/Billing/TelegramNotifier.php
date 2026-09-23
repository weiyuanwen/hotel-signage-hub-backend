<?php

namespace App\Domains\Billing;

use App\Models\BillingOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function notifyPaid(BillingOrder $order): void
    {
        $amount = $order->method === 'stripe'
            ? '$'.number_format($order->amount_usd_cents / 100, 2)
            : number_format($order->amount_vnd).' VND';

        $this->send(implode("\n", [
            'SignageHub: thanh toán thành công',
            'Mã: '.$order->order_code,
            'Gói: '.$order->plan,
            'Cổng: '.$order->method,
            'Số tiền: '.$amount,
            'Email: '.$order->email,
            'Khách sạn: '.($order->hotel?->name ?? $order->hotel_name ?? '—'),
            'Hết hạn: '.($order->period_ends_at?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') ?? '—'),
        ]));
    }

    public function notifyExpired(BillingOrder $order): void
    {
        $this->send(implode("\n", [
            'SignageHub: đơn hết hạn / chưa CK',
            'Mã: '.$order->order_code,
            'Email: '.$order->email,
            'Số tiền: '.number_format($order->amount_vnd).' VND',
        ]));
    }

    public function send(string $text): bool
    {
        $token = (string) config('services.telegram.bot_token');
        $chat = (string) config('services.telegram.chat_id');
        if ($token === '' || $chat === '') {
            return false;
        }

        try {
            $res = Http::timeout(8)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chat,
                'text' => $text,
            ]);

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('telegram.notify_failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
