<?php

use App\Domains\Realtime\ChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('room.{hotelId}.{roomId}', function ($user, $hotelId, $roomId) {
    return app(ChannelAuthorizer::class)->canJoinRoom($user, (int) $hotelId, (int) $roomId);
});

Broadcast::channel('device.{deviceId}', function ($user, $deviceId) {
    return app(ChannelAuthorizer::class)->canJoinDevice($user, (int) $deviceId);
});
