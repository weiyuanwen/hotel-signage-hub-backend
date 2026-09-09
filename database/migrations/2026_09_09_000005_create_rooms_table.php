<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->string('code');
            $table->string('name')->nullable();
            $table->string('kind')->default('guest'); // guest|public
            $table->unsignedBigInteger('current_welcome_id')->nullable();
            $table->unsignedInteger('content_revision')->default(0);
            $table->unsignedBigInteger('default_media_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hotel_id', 'code']);
            $table->index(['hotel_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
