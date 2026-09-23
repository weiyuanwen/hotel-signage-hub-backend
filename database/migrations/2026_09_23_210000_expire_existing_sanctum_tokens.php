<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('personal_access_tokens')
            ->whereNull('expires_at')
            ->where('name', 'cms')
            ->update(['expires_at' => $now->copy()->addDays(7)]);

        DB::table('personal_access_tokens')
            ->whereNull('expires_at')
            ->where('name', 'tv')
            ->update(['expires_at' => $now->copy()->addDays(30)]);
    }
};
