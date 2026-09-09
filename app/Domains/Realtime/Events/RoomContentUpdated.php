<?php

namespace App\Domains\Realtime\Events;

use App\Models\Room;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomContentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Room $room,
        public array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("room.{$this->room->hotel_id}.{$this->room->id}");
    }

    public function broadcastAs(): string
    {
        return 'content.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
