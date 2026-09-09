<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welcome_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->foreignId('room_id')->constrained();
            $table->string('guest_display_name');
            $table->text('message')->nullable();
            $table->string('locale', 8)->default('vi');
            $table->string('source')->default('manual'); // manual|pms
            $table->string('external_ref')->nullable();
            $table->boolean('is_current')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['room_id', 'is_current']);
            $table->index(['hotel_id', 'external_ref']);
            $table->index(['room_id', 'checked_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welcome_contents');
    }
};
