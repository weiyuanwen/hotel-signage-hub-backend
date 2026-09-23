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
            $table->timestamp('subscription_expires_at')->nullable()->after('device_limit');
        });

        DB::table('hotels')->where('plan', 'free')->update(['device_limit' => 1]);
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('subscription_expires_at');
        });
    }
};
