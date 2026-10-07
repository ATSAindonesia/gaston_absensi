<?php

use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\Court;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->sport = Sport::factory()->create(['name' => 'Badminton']);
    $this->court = Court::factory()->for($this->sport)->create(['name' => 'Lapangan A']);
    $this->sportPadel = Sport::factory()->create(['name' => 'Padel']);
    $this->courtPadel = Court::factory()->for($this->sportPadel)->create(['name' => 'Lapangan Padel']);
});

test('anggota cannot access session pages', function () {
    $anggota = User::factory()->anggota()->create();
    $session = AttendanceSession::factory()->create();

    $this->actingAs($anggota)->get(route('admin.sessions'))->assertForbidden();
    $this->actingAs($anggota)->get(route('admin.sessions.show', $session))->assertForbidden();
});

test('admin can access session pages', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create();

    $this->actingAs($admin)->get(route('admin.sessions'))->assertOk();
    $this->actingAs($admin)->get(route('admin.sessions.show', $session))->assertOk();
});

test('admin can create a session with status soon', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Latihan Jumat')
        ->set('sport_id', $this->sport->id)
        ->set('court_id', $this->court->id)
        ->set('session_date', '2026-10-10')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession')
        ->assertHasNoErrors();

    $session = AttendanceSession::where('name', 'Latihan Jumat')->first();

    expect($session)->not->toBeNull();
    expect($session->status)->toBe(SessionStatus::Soon);
    expect($session->created_by)->toBe($admin->id);
});

test('admin can update a scheduled session', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('openEdit', $session->id)
        ->set('name', 'Nama Baru')
        ->set('sport_id', $this->sportPadel->id)
        ->set('court_id', $this->courtPadel->id)
        ->set('session_date', '2026-10-11')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession')
        ->assertHasNoErrors();

    expect($session->refresh()->name)->toBe('Nama Baru');
    expect($session->refresh()->sport_id)->toBe($this->sportPadel->id);
    expect($session->refresh()->court_id)->toBe($this->courtPadel->id);
});

test('session validation requires end time after start time', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Invalid')
        ->set('sport_id', $this->sport->id)
        ->set('court_id', $this->court->id)
        ->set('session_date', '2026-10-10')
        ->set('start_time', '12:00')
        ->set('end_time', '10:00')
        ->call('saveSession')
        ->assertHasErrors(['end_time']);

    expect(AttendanceSession::where('name', 'Invalid')->exists())->toBeFalse();
});

test('session validation rejects past dates', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Invalid Past')
        ->set('sport_id', $this->sport->id)
        ->set('court_id', $this->court->id)
        ->set('session_date', '2020-01-01')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession')
        ->assertHasErrors(['session_date']);

    expect(AttendanceSession::where('name', 'Invalid Past')->exists())->toBeFalse();
});

test('session validation rejects non-24-hour time formats', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Invalid Format')
        ->set('sport_id', $this->sport->id)
        ->set('court_id', $this->court->id)
        ->set('session_date', '2026-10-10')
        ->set('start_time', '2:30 PM')
        ->set('end_time', '25:00')
        ->call('saveSession')
        ->assertHasErrors(['start_time', 'end_time']);

    expect(AttendanceSession::where('name', 'Invalid Format')->exists())->toBeFalse();
});

test('saved session combines date and times into datetimes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Gabungan Waktu')
        ->set('sport_id', $this->sport->id)
        ->set('court_id', $this->court->id)
        ->set('session_date', '2026-10-10')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession')
        ->assertHasNoErrors();

    $session = AttendanceSession::where('name', 'Gabungan Waktu')->first();

    expect($session->starts_at->format('Y-m-d H:i'))->toBe('2026-10-10 10:00');
    expect($session->ends_at->format('Y-m-d H:i'))->toBe('2026-10-10 12:00');
});

test('admin cannot create a new session while one is active', function () {
    $admin = User::factory()->admin()->create();
    AttendanceSession::factory()->active()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Sesi Kedua')
        ->set('sport_id', $this->sportPadel->id)
        ->set('court_id', $this->courtPadel->id)
        ->set('session_date', '2026-10-10')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession');

    expect(AttendanceSession::where('name', 'Sesi Kedua')->exists())->toBeFalse();
});

test('only one session can be active at a time', function () {
    $admin = User::factory()->admin()->create();
    $first = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $second = AttendanceSession::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')->call('startSession', $first->id);

    expect($first->refresh()->status)->toBe(SessionStatus::Active);

    Livewire::test('pages::admin.sessions')->call('startSession', $second->id);

    expect($second->refresh()->status)->toBe(SessionStatus::Soon);
});

test('admin can finish active session then create a new one', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->active()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')->call('finishSession', $session->id);

    expect($session->refresh()->status)->toBe(SessionStatus::Finished);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Sesi Baru Setelah Selesai')
        ->set('sport_id', $this->sport->id)
        ->set('court_id', $this->court->id)
        ->set('session_date', '2026-10-12')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession')
        ->assertHasNoErrors();

    expect(AttendanceSession::where('name', 'Sesi Baru Setelah Selesai')->exists())->toBeTrue();
});

test('admin can cancel active session then create a new one', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->active()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('openCancel', $session->id)
        ->set('cancel_reason', 'Hujan deras')
        ->call('cancelSession')
        ->assertHasNoErrors();

    $session->refresh();

    expect($session->status)->toBe(SessionStatus::Cancelled);
    expect($session->cancel_reason)->toBe('Hujan deras');

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Sesi Baru Setelah Batal')
        ->set('sport_id', $this->sportPadel->id)
        ->set('court_id', $this->courtPadel->id)
        ->set('session_date', '2026-10-12')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession')
        ->assertHasNoErrors();

    expect(AttendanceSession::where('name', 'Sesi Baru Setelah Batal')->exists())->toBeTrue();
});

test('admin can delete a non-active session', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('confirmDelete', $session->id)
        ->call('deleteSession')
        ->assertHasNoErrors();

    expect(AttendanceSession::where('id', $session->id)->exists())->toBeFalse();
});

test('admin cannot delete an active session', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->active()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('confirmDelete', $session->id)
        ->call('deleteSession');

    expect(AttendanceSession::where('id', $session->id)->exists())->toBeTrue();
});

test('admin can bulk register many users into a session', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $users = User::factory()->count(5)->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->set('selectedUsers', $users->pluck('id')->all())
        ->call('addBulkParticipants')
        ->assertHasNoErrors();

    expect($session->participants()->count())->toBe(5);
});

test('bulk registration skips already registered users without duplicates', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $users = User::factory()->count(3)->create();
    $session->participants()->syncWithoutDetaching([$users[0]->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->set('selectedUsers', $users->pluck('id')->all())
        ->call('addBulkParticipants')
        ->assertHasNoErrors();

    expect($session->participants()->count())->toBe(3);
    expect($session->participants()->where('user_id', $users[0]->id)->count())->toBe(1);
});

test('admin can add and remove a participant via bulk add', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $user = User::factory()->create();

    $this->actingAs($admin);

    $component = Livewire::test('pages::admin.session-detail', ['session' => $session]);

    $component->set('selectedUsers', [$user->id])->call('addBulkParticipants')->assertHasNoErrors();

    expect($session->participants()->where('user_id', $user->id)->exists())->toBeTrue();

    // Duplicate is skipped
    $component->set('selectedUsers', [$user->id])->call('addBulkParticipants');

    expect($session->participants()->where('user_id', $user->id)->count())->toBe(1);

    // Remove (participant detached, user record kept)
    $component->call('confirmRemove', $user->id)->call('removeParticipant')->assertHasNoErrors();

    expect($session->participants()->where('user_id', $user->id)->exists())->toBeFalse();
    expect(User::where('id', $user->id)->exists())->toBeTrue();
});
