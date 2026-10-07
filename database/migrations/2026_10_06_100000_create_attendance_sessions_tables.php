<?php

use App\Enums\SessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location');
            $table->string('type')->default('badminton');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default(SessionStatus::Soon->value);
            $table->text('cancel_reason')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('starts_at');
        });

        Schema::create('attendance_session_user', function (Blueprint $table) {
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['attendance_session_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_session_user');
        Schema::dropIfExists('attendance_sessions');
    }
};
