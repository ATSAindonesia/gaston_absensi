<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('member_code', 11)->nullable()->unique()->after('email');
        });

        // Backfill existing users with sequential codes (GSTN-000001, ...).
        $last = DB::table('users')
            ->whereNotNull('member_code')
            ->orderByDesc('member_code')
            ->value('member_code');

        $next = $last !== null ? ((int) substr($last, 5)) + 1 : 1;

        $userIds = DB::table('users')
            ->whereNull('member_code')
            ->orderBy('id')
            ->pluck('id');

        foreach ($userIds as $id) {
            DB::table('users')->where('id', $id)->update([
                'member_code' => sprintf('GSTN-%06d', $next++),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['member_code']);
            $table->dropColumn('member_code');
        });
    }
};
