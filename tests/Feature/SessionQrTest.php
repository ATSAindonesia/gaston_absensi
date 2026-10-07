<?php

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\SessionQr;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('starting a session generates qr secret and finishing clears it', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);

    expect($session->qr_secret)->toBeNull();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')->call('startSession', $session->id);

    expect($session->refresh()->qr_secret)->not->toBeNull();

    Livewire::test('pages::admin.sessions')->call('finishSession', $session->id);

    expect($session->refresh()->qr_secret)->toBeNull();
});

test('cancelling a session clears qr secret', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->active()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('openCancel', $session->id)
        ->call('cancelSession');

    expect($session->refresh()->qr_secret)->toBeNull();
});

test('token changes every 10 second window', function () {
    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);

    $now = time();
    $window = $now - ($now % SessionQr::WINDOW_SECONDS);

    expect(SessionQr::tokenFor($session, $window))->toBe(SessionQr::tokenFor($session, $window + 5));
    expect(SessionQr::tokenFor($session, $window))->not->toBe(SessionQr::tokenFor($session, $window + 10));
});

test('registered participant can scan and attendance is recorded once', function () {
    // Freeze time mid-window so the token cannot roll over mid-test.
    $windowStart = intdiv(now()->timestamp, SessionQr::WINDOW_SECONDS) * SessionQr::WINDOW_SECONDS;
    Carbon\Carbon::setTestNow(Carbon\Carbon::createFromTimestamp($windowStart + 5));

    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $token = SessionQr::tokenFor($session);
    $url = route('attendance.scan', ['token' => $token], absolute: false);

    $first = $this->actingAs($member)->get($url);
    $first->assertOk();
    $first->assertSee('Kehadiran Tercatat');

    $second = $this->actingAs($member)->get($url);
    $second->assertOk();
    $second->assertSee('Sudah Tercatat');

    expect(Attendance::where('attendance_session_id', $session->id)->where('user_id', $member->id)->count())->toBe(1);

    $attendance = Attendance::first();
    expect($attendance->scanned_at)->not->toBeNull();

    Carbon\Carbon::setTestNow();
});

test('expired token from previous window is rejected', function () {
    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $oldToken = SessionQr::tokenFor($session, time() - 30);

    $response = $this->actingAs($member)->get(route('attendance.scan', ['token' => $oldToken], absolute: false));

    $response->assertStatus(410);
    $response->assertSee('QR Kedaluwarsa');

    expect(Attendance::count())->toBe(0);
});

test('unregistered user cannot scan', function () {
    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);
    $outsider = User::factory()->anggota()->create();

    $response = $this->actingAs($outsider)->get(route('attendance.scan', ['token' => SessionQr::tokenFor($session)], absolute: false));

    $response->assertForbidden();
    $response->assertSee('Tidak Terdaftar');

    expect(Attendance::count())->toBe(0);
});

test('guest is redirected to login when scanning', function () {
    $session = AttendanceSession::factory()->active()->create(['qr_secret' => SessionQr::newSecret()]);

    $response = $this->get(route('attendance.scan', ['token' => SessionQr::tokenFor($session)], absolute: false));

    $response->assertRedirect(route('login'));
});

test('unknown token is rejected', function () {
    $member = User::factory()->anggota()->create();

    $response = $this->actingAs($member)->get(route('attendance.scan', ['token' => str_repeat('0', 64)], absolute: false));

    $response->assertStatus(410);
});

test('qr preview page is only for owner or superadmin on active sessions', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();
    $active = AttendanceSession::factory()->active()->create(['created_by' => $owner->id, 'qr_secret' => SessionQr::newSecret()]);
    $soon = AttendanceSession::factory()->create(['created_by' => $owner->id, 'qr_secret' => SessionQr::newSecret()]);

    $this->actingAs($owner)->get(route('admin.sessions.qr', $active))->assertOk();
    $this->actingAs($superadmin)->get(route('admin.sessions.qr', $active))->assertOk();
    $this->actingAs($other)->get(route('admin.sessions.qr', $active))->assertNotFound();
    $this->actingAs($owner)->get(route('admin.sessions.qr', $soon))->assertNotFound();
});

test('detail page shows qr preview button only for active manageable sessions', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $active = AttendanceSession::factory()->active()->create(['created_by' => $owner->id]);
    $soon = AttendanceSession::factory()->create(['created_by' => $owner->id]);

    $this->actingAs($owner)->get(route('admin.sessions.show', $active))->assertSee('Preview QR');
    $this->actingAs($owner)->get(route('admin.sessions.show', $soon))->assertDontSee('Preview QR');
    $this->actingAs($other)->get(route('admin.sessions.show', $active))->assertDontSee('Preview QR');
});
