<?php

namespace App\Domains\Content;

use App\Domains\Device\ScreenDataBuilder;
use App\Domains\Realtime\Events\RoomContentUpdated;
use App\Models\Room;
use App\Models\User;
use App\Models\WelcomeContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StayService
{
    public function __construct(private ScreenDataBuilder $screenData) {}

    /**
     * @param  array{guest_display_name: string, message?: ?string, locale?: ?string, source?: ?string, external_ref?: ?string}  $data
     */
    public function checkIn(Room $room, User $actor, array $data): WelcomeContent
    {
        $this->assertRoomActive($room);

        return DB::transaction(function () use ($room, $actor, $data) {
            $room = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->current_welcome_id) {
                $this->closeCurrentStay($room, $actor);
            }

            $stay = WelcomeContent::query()->create([
                'hotel_id' => $room->hotel_id,
                'room_id' => $room->id,
                'guest_display_name' => $data['guest_display_name'],
                'message' => $data['message'] ?? null,
                'locale' => $data['locale'] ?? $room->hotel->default_locale,
                'source' => $data['source'] ?? 'manual',
                'external_ref' => $data['external_ref'] ?? null,
                'is_current' => true,
                'checked_in_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $room->forceFill(['current_welcome_id' => $stay->id])->save();
            $room->bumpRevision();

            $this->broadcast($room->fresh(['hotel', 'currentWelcome', 'defaultMedia']));

            return $stay->fresh();
        });
    }

    public function checkout(Room $room, User $actor): void
    {
        DB::transaction(function () use ($room, $actor) {
            $room = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            if (! $room->current_welcome_id) {
                throw ValidationException::withMessages([
                    'room' => 'Room has no current stay.',
                ]);
            }

            $this->closeCurrentStay($room, $actor);
            $room->forceFill(['current_welcome_id' => null])->save();
            $room->bumpRevision();

            $this->broadcast($room->fresh(['hotel', 'currentWelcome', 'defaultMedia']));
        });
    }

    /**
     * @param  array{guest_display_name?: string, message?: ?string, locale?: ?string}  $data
     */
    public function updateCurrent(Room $room, User $actor, array $data): WelcomeContent
    {
        $stay = $room->currentWelcome;

        if (! $stay) {
            throw ValidationException::withMessages([
                'room' => 'Room has no current stay.',
            ]);
        }

        $stay->fill($data);
        $stay->updated_by = $actor->id;
        $stay->save();

        $room->bumpRevision();
        $this->broadcast($room->fresh(['hotel', 'currentWelcome', 'defaultMedia']));

        return $stay->fresh();
    }

    private function closeCurrentStay(Room $room, User $actor): void
    {
        WelcomeContent::query()
            ->whereKey($room->current_welcome_id)
            ->update([
                'is_current' => null,
                'checked_out_at' => now(),
                'updated_by' => $actor->id,
            ]);
    }

    private function assertRoomActive(Room $room): void
    {
        if (! $room->is_active) {
            throw ValidationException::withMessages([
                'room' => 'Room is inactive.',
            ]);
        }
    }

    private function broadcast(Room $room): void
    {
        $hotel = $room->hotel;
        event(new RoomContentUpdated($room, $this->screenData->forRoom($hotel, $room)));
    }
}
