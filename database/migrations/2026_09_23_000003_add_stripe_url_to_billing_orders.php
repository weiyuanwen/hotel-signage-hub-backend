<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_orders', function (Blueprint $table) {
            $table->text('stripe_url')->nullable()->after('stripe_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('billing_orders', function (Blueprint $table) {
            $table->dropColumn('stripe_url');
        });
    }
};
