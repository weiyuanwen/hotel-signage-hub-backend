<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('wifi_ssid', 64)->nullable()->after('weather_region');
            $table->string('wifi_password', 64)->nullable()->after('wifi_ssid');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['wifi_ssid', 'wifi_password']);
        });
    }
};
