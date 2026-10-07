<?php

use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\SessionQr;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('participants default to tidak hadir on session detail', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $response = $this->actingAs($admin)->get(route('admin.sessions.show', $session));

    $response->assertOk();
    $response->assertSee('Status Hadir');
    $response->assertSee('Tidak Hadir');
    $response->assertDontSee('>Role<', false);
});

test('scanned participant shows hadir with scan time', function () {
    $windowStart = intdiv(now()->timestamp, SessionQr::WINDOW_SECONDS) * SessionQr::WINDOW_SECONDS;
    Carbon\Carbon::setTestNow(Carbon\Carbon::createFromTimestamp($windowStart + 5));

    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->active()->create([
        'created_by' => $admin->id,
        'qr_secret' => SessionQr::newSecret(),
    ]);
    $present = User::factory()->anggota()->create();
    $absent = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$present->id, $absent->id]);

    $this->actingAs($present)->get(route('attendance.scan', ['token' => SessionQr::tokenFor($session)], absolute: false))->assertOk();

    $response = $this->actingAs($admin)->get(route('admin.sessions.show', $session));

    $response->assertOk();
    $response->assertSee('Hadir');
    $response->assertSee('Tidak Hadir');
    $response->assertSee($present->fresh()->attendances()->first()->scanned_at->format('H:i'));

    Carbon\Carbon::setTestNow();
});

test('attendance status survives participant search filter', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);
    $session->attendances()->create(['user_id' => $member->id, 'scanned_at' => now()]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->set('participantSearch', $member->member_code)
        ->assertSee('Hadir');
});
