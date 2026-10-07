<?php

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\SessionQr;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('token from immediately previous window is still accepted (10s grace)', function () {
    $windowStart = intdiv(now()->timestamp, SessionQr::WINDOW_SECONDS) * SessionQr::WINDOW_SECONDS;
    Carbon\Carbon::setTestNow(Carbon\Carbon::createFromTimestamp($windowStart + 5));

    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $previousToken = SessionQr::tokenFor($session, ($windowStart - SessionQr::WINDOW_SECONDS) + 5);

    $response = $this->actingAs($member)->get(route('attendance.scan', ['token' => $previousToken], absolute: false));

    $response->assertOk();
    $response->assertSee('Kehadiran Tercatat');

    expect(Attendance::where('attendance_session_id', $session->id)->where('user_id', $member->id)->count())->toBe(1);

    Carbon\Carbon::setTestNow();
});

test('participant added mid-session can scan the current qr', function () {
    $windowStart = intdiv(now()->timestamp, SessionQr::WINDOW_SECONDS) * SessionQr::WINDOW_SECONDS;
    Carbon\Carbon::setTestNow(Carbon\Carbon::createFromTimestamp($windowStart + 5));

    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $lateMember = User::factory()->anggota()->create();

    // Simulasi admin lupa lalu menambahkan anggota setelah sesi berjalan.
    $session->participants()->syncWithoutDetaching([$lateMember->id]);

    $token = SessionQr::tokenFor($session);

    $response = $this->actingAs($lateMember)->get(route('attendance.scan', ['token' => $token], absolute: false));

    $response->assertOk();
    $response->assertSee('Kehadiran Tercatat');

    expect(Attendance::where('attendance_session_id', $session->id)->where('user_id', $lateMember->id)->count())->toBe(1);

    Carbon\Carbon::setTestNow();
});

test('late participant scanning via member scan api with previous window token succeeds', function () {
    $windowStart = intdiv(now()->timestamp, SessionQr::WINDOW_SECONDS) * SessionQr::WINDOW_SECONDS;
    Carbon\Carbon::setTestNow(Carbon\Carbon::createFromTimestamp($windowStart + 5));

    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $lateMember = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$lateMember->id]);

    $previousToken = SessionQr::tokenFor($session, ($windowStart - SessionQr::WINDOW_SECONDS) + 5);

    $response = $this->actingAs($lateMember)->postJson(route('member.scan'), ['token' => $previousToken]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);

    expect(Attendance::where('attendance_session_id', $session->id)->where('user_id', $lateMember->id)->count())->toBe(1);

    Carbon\Carbon::setTestNow();
});

test('token older than grace window is still rejected', function () {
    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $oldToken = SessionQr::tokenFor($session, time() - 30);

    $response = $this->actingAs($member)->get(route('attendance.scan', ['token' => $oldToken], absolute: false));

    $response->assertStatus(410);
    $response->assertSee('QR Kedaluwarsa');

    expect(Attendance::count())->toBe(0);
});
