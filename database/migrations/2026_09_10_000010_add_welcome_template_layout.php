<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_welcome_templates', function (Blueprint $table) {
            $table->json('layout')->nullable();
            $table->foreignId('background_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_welcome_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('background_media_id');
            $table->dropColumn('layout');
        });
    }
};
