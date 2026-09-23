<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 24)->unique();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('hotel_name')->nullable();
            $table->string('plan', 16);
            $table->string('method', 16);
            $table->unsignedInteger('amount_vnd');
            $table->unsignedInteger('amount_usd_cents');
            $table->string('status', 16)->default('pending');
            $table->string('locale', 8)->default('vi');
            $table->string('transfer_content', 24)->nullable();
            $table->text('qr_image_url')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_order_id')->constrained('billing_orders')->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('provider_txn_id');
            $table->unsignedInteger('amount');
            $table->string('currency', 8);
            $table->string('description')->nullable();
            $table->timestamp('matched_at');
            $table->timestamps();
            $table->unique(['provider', 'provider_txn_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
        Schema::dropIfExists('billing_orders');
    }
};
