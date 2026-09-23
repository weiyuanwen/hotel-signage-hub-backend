<?php

namespace App\Domains\Realtime;

use Illuminate\Broadcasting\BroadcastException;

final class SafeBroadcast
{
    public static function send(object $event): void
    {
        try {
            event($event);
        } catch (BroadcastException $e) {
            report($e);
        }
    }
}
