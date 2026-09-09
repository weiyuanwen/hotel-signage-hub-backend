<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('default_welcome_template_key', 32)->default('dusk');
        });

        Schema::table('welcome_contents', function (Blueprint $table) {
            $table->string('template_key', 32)->default('dusk');
        });

        Schema::create('hotel_welcome_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 32);
            $table->boolean('is_enabled')->default(true);
            $table->string('display_name', 40)->nullable();
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();
            $table->unique(['hotel_id', 'template_key']);
        });

        $catalog = [
            ['dusk', 1],
            ['linen', 2],
            ['harbor', 3],
            ['garden', 4],
            ['stone', 5],
        ];

        $now = now();
        foreach (DB::table('hotels')->pluck('id') as $hotelId) {
            foreach ($catalog as [$key, $order]) {
                DB::table('hotel_welcome_templates')->insert([
                    'hotel_id' => $hotelId,
                    'template_key' => $key,
                    'is_enabled' => true,
                    'display_name' => null,
                    'sort_order' => $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('hotels')->where('id', $hotelId)->update([
                'default_welcome_template_key' => 'dusk',
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_welcome_templates');
        Schema::table('welcome_contents', function (Blueprint $table) {
            $table->dropColumn('template_key');
        });
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('default_welcome_template_key');
        });
    }
};
