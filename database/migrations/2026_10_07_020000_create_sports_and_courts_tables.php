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
        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['sport_id', 'name']);
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('sport_id')->nullable()->after('created_by')->constrained()->restrictOnDelete();
            $table->foreignId('court_id')->nullable()->after('sport_id')->constrained()->restrictOnDelete();
        });

        // Backfill master data from legacy string columns.
        $types = DB::table('attendance_sessions')
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type');

        $sportIds = [];

        foreach ($types as $type) {
            $sportIds[$type] = DB::table('sports')->insertGetId([
                'name' => ucfirst($type),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sessions = DB::table('attendance_sessions')->get(['id', 'type', 'location']);

        foreach ($sessions as $session) {
            $sportId = $sportIds[$session->type] ?? null;

            $courtId = null;

            if ($sportId !== null && $session->location !== null) {
                $courtId = DB::table('courts')
                    ->where('sport_id', $sportId)
                    ->where('name', $session->location)
                    ->value('id');

                if ($courtId === null) {
                    $courtId = DB::table('courts')->insertGetId([
                        'sport_id' => $sportId,
                        'name' => $session->location,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('attendance_sessions')->where('id', $session->id)->update([
                'sport_id' => $sportId,
                'court_id' => $courtId,
            ]);
        }

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['type', 'location']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->string('type')->nullable()->after('created_by');
            $table->string('location')->nullable()->after('type');
        });

        // Best-effort restore of legacy string columns.
        $sessions = DB::table('attendance_sessions as s')
            ->leftJoin('sports as sp', 'sp.id', '=', 's.sport_id')
            ->leftJoin('courts as c', 'c.id', '=', 's.court_id')
            ->get(['s.id', 'sp.name as sport_name', 'c.name as court_name']);

        foreach ($sessions as $session) {
            DB::table('attendance_sessions')->where('id', $session->id)->update([
                'type' => $session->sport_name !== null ? strtolower($session->sport_name) : null,
                'location' => $session->court_name,
            ]);
        }

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('court_id');
            $table->dropConstrainedForeignId('sport_id');
        });

        Schema::dropIfExists('courts');
        Schema::dropIfExists('sports');
    }
};
