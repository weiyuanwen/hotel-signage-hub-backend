<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('Asia/Ho_Chi_Minh');
            $table->string('default_locale', 8)->default('vi');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->unsignedBigInteger('default_media_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
