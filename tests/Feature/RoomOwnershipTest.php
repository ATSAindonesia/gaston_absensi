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

test('admin can view but cannot edit another admin room', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $owner->id]);

    // Read access is allowed
    $this->actingAs($other)->get(route('admin.sessions'))->assertOk();
    $this->actingAs($other)->get(route('admin.sessions.show', $session))->assertOk();

    $this->actingAs($other);

    // Edit form cannot be opened for another admin's room
    Livewire::test('pages::admin.sessions')
        ->call('openEdit', $session->id)
        ->assertSet('sessionId', null);

    // Direct save on another admin's room changes nothing
    Livewire::test('pages::admin.sessions')
        ->set('sessionId', $session->id)
        ->set('name', 'Diambil Alih')
        ->set('sport_id', $this->sportPadel->id)
        ->set('court_id', $this->courtPadel->id)
        ->set('session_date', '2026-10-11')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession');

    expect($session->refresh()->name)->not->toBe('Diambil Alih');
});

test('admin cannot start, finish, cancel, or delete another admin room', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $owner->id]);

    $this->actingAs($other);

    Livewire::test('pages::admin.sessions')->call('startSession', $session->id);
    expect($session->refresh()->status)->toBe(SessionStatus::Soon);

    Livewire::test('pages::admin.sessions')->call('openCancel', $session->id)->call('cancelSession');
    expect($session->refresh()->status)->toBe(SessionStatus::Soon);

    Livewire::test('pages::admin.sessions')->call('confirmDelete', $session->id)->call('deleteSession');
    expect(AttendanceSession::where('id', $session->id)->exists())->toBeTrue();

    // Finish path on an active room owned by someone else
    $active = AttendanceSession::factory()->active()->create(['created_by' => $owner->id]);
    Livewire::test('pages::admin.sessions')->call('finishSession', $active->id);
    expect($active->refresh()->status)->toBe(SessionStatus::Active);
});

test('admin cannot manage participants of another admin room', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $owner->id]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $this->actingAs($other);

    $component = Livewire::test('pages::admin.session-detail', ['session' => $session]);

    // Read-only mode is active
    $component->assertSet('isReadOnly', true);

    // Bulk add blocked
    $candidate = User::factory()->create();
    $component->set('selectedUsers', [$candidate->id])->call('addBulkParticipants');
    expect($session->participants()->where('user_id', $candidate->id)->exists())->toBeFalse();

    // Remove blocked (existing participant stays)
    $component->call('confirmRemove', $member->id)->call('removeParticipant');
    expect($session->participants()->where('user_id', $member->id)->exists())->toBeTrue();
});

test('owner sees manageable detail page', function () {
    $owner = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->assertSet('isReadOnly', false);
});

test('superadmin can manage another admin room', function () {
    $owner = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $owner->id]);

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.sessions')->call('startSession', $session->id);
    expect($session->refresh()->status)->toBe(SessionStatus::Active);

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->assertSet('isReadOnly', false);
});

test('sessions list has no cabor or room filters and shows all rooms without grouping', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    AttendanceSession::factory()->create(['created_by' => $admin->id, 'name' => 'Room Saya Tanpa Filter Unik']);
    AttendanceSession::factory()->create(['created_by' => $other->id, 'name' => 'Room Orang Tanpa Filter Unik']);

    $html = $this->actingAs($admin)->get(route('admin.sessions'))->getContent();

    expect($html)->toContain('Room Saya Tanpa Filter Unik');
    expect($html)->toContain('Room Orang Tanpa Filter Unik');
    expect($html)->not->toContain('Semua Cabor');
    expect($html)->not->toContain('Semua Room');
    expect($html)->not->toContain('data-test="group-mine-heading"');
    expect($html)->not->toContain('data-test="group-others-divider"');
});

test('session card displays admin name, court, time, status, and sport', function () {
    $admin = User::factory()->admin()->create();
    $court = Court::factory()->for($this->sportPadel)->create(['name' => 'Lapangan Card']);
    $session = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'name' => 'Sesi Card Unik',
        'sport_id' => $this->sportPadel->id,
        'court_id' => $court->id,
        'starts_at' => '2026-10-10 10:00:00',
        'ends_at' => '2026-10-10 12:00:00',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.sessions'));

    $response->assertOk();
    $response->assertSee($admin->name);
    $response->assertSee('Lapangan Card');
    $response->assertSee('Padel');
    $response->assertSee($session->status->label());
});

test('session model ownership helpers work', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);

    expect($session->isOwnedBy($admin))->toBeTrue();
    expect($session->isOwnedBy($other))->toBeFalse();
    expect(AttendanceSession::ownedBy($admin)->pluck('id')->all())->toContain($session->id);
    expect(AttendanceSession::ownedBy($other)->pluck('id')->all())->not->toContain($session->id);
});
