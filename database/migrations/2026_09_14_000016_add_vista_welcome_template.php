<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (DB::table('hotels')->pluck('id') as $hotelId) {
            $exists = DB::table('hotel_welcome_templates')
                ->where('hotel_id', $hotelId)
                ->where('template_key', 'vista')
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('hotel_welcome_templates')->insert([
                'hotel_id' => $hotelId,
                'template_key' => 'vista',
                'is_enabled' => true,
                'display_name' => null,
                'sort_order' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('hotel_welcome_templates')->where('template_key', 'vista')->delete();
    }
};
