<?php

namespace App\Http\Controllers\Api\Device;

use App\Domains\Auth\AccessTokenFactory;
use App\Domains\Device\HeartbeatService;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function __construct(
        private HeartbeatService $heartbeat,
        private AccessTokenFactory $tokens,
    ) {}

    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $lastSeenBefore = $device->last_seen_at;
        $this->heartbeat->touch($device);
        $this->tokens->renewDeviceIfDue($device);
        $device->loadMissing('room');

        return response()->json([
            'online' => true,
            'content_revision' => $device->room?->content_revision,
            'last_seen_at_unchanged' => $device->fresh()->last_seen_at?->equalTo($lastSeenBefore) ?? $lastSeenBefore === null,
        ]);
    }
}
