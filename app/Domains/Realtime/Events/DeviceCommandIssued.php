<?php

namespace App\Domains\Realtime\Events;

use App\Models\Device;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceCommandIssued implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Device $device,
        public string $command,
        public array $payload = [],
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("device.{$this->device->id}");
    }

    public function broadcastAs(): string
    {
        return 'device.command';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'command' => $this->command,
            ...$this->payload,
        ];
    }
}
