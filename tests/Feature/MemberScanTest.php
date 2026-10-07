<?php

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\SessionQr;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('member dashboard shows start camera button', function () {
    $member = User::factory()->anggota()->create();

    $response = $this->actingAs($member)->get(route('member.dashboard'));

    $response->assertOk();
    $response->assertSee('Mulai Kamera');
    $response->assertSee('qr-reader', false);
    $response->assertSee('html5-qrcode', false);
    $response->assertSee('btn-cancel-camera', false);
});

test('registered participant can scan via token and attendance is recorded once', function () {
    $windowStart = intdiv(now()->timestamp, SessionQr::WINDOW_SECONDS) * SessionQr::WINDOW_SECONDS;
    Carbon\Carbon::setTestNow(Carbon\Carbon::createFromTimestamp($windowStart + 5));

    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $token = SessionQr::tokenFor($session);

    $first = $this->actingAs($member)->postJson(route('member.scan'), ['token' => $token]);
    $first->assertOk();
    $first->assertJson(['ok' => true, 'already' => false]);

    $second = $this->actingAs($member)->postJson(route('member.scan'), ['token' => $token]);
    $second->assertOk();
    $second->assertJson(['ok' => true, 'already' => true]);

    expect(Attendance::where('attendance_session_id', $session->id)->where('user_id', $member->id)->count())->toBe(1);

    Carbon\Carbon::setTestNow();
});

test('member can scan via full scan url', function () {
    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $scanUrl = SessionQr::scanUrl($session);

    $response = $this->actingAs($member)->postJson(route('member.scan'), ['scan_url' => $scanUrl]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);
    expect(Attendance::where('attendance_session_id', $session->id)->where('user_id', $member->id)->count())->toBe(1);
});

test('expired token from previous window is rejected', function () {
    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $oldToken = SessionQr::tokenFor($session, time() - 30);

    $response = $this->actingAs($member)->postJson(route('member.scan'), ['token' => $oldToken]);

    $response->assertStatus(410);
    $response->assertJson(['ok' => false, 'reason' => 'expired']);
    expect(Attendance::count())->toBe(0);
});

test('unregistered user cannot scan', function () {
    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $outsider = User::factory()->anggota()->create();

    $response = $this->actingAs($outsider)->postJson(route('member.scan'), ['token' => SessionQr::tokenFor($session)]);

    $response->assertForbidden();
    $response->assertJson(['ok' => false, 'reason' => 'forbidden']);
    expect(Attendance::count())->toBe(0);
});

test('scan requires token or scan url', function () {
    $member = User::factory()->anggota()->create();

    $response = $this->actingAs($member)->postJson(route('member.scan'), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});

test('guest is redirected to login when opening member dashboard or scanning', function () {
    $this->get(route('member.dashboard'))->assertRedirect(route('login'));
    $this->postJson(route('member.scan'), ['token' => 'abc'])->assertUnauthorized();
});

test('admin is redirected from member dashboard to admin area', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('member.dashboard'))->assertRedirect(route('admin.dashboard'));
});
