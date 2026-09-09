<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // image|video|logo
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
