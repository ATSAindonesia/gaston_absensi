<?php

use App\Models\AttendanceSession;
use App\Models\Court;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->sport = Sport::factory()->create(['name' => 'Badminton']);
    $this->gor1 = Court::factory()->for($this->sport)->create(['name' => 'gor-1']);
    $this->gor2 = Court::factory()->for($this->sport)->create(['name' => 'gor-2']);
});

test('member in overlapping session is hidden from add pool', function () {
    $admin = User::factory()->admin()->create();
    $busy = User::factory()->anggota()->create(['name' => 'Sibuk Bentrok Unik']);
    $free = User::factory()->anggota()->create(['name' => 'Bebas Tersedia Unik']);

    $gor1 = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'sport_id' => $this->sport->id,
        'court_id' => $this->gor1->id,
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);
    $gor1->participants()->syncWithoutDetaching([$busy->id]);

    $gor2 = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'sport_id' => $this->sport->id,
        'court_id' => $this->gor2->id,
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $gor2])
        ->assertDontSee('Sibuk Bentrok Unik')
        ->assertSee('Bebas Tersedia Unik');
});

test('member is available outside overlapping time and on boundary touch', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->anggota()->create(['name' => 'Batas Sentuh Unik']);

    $first = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);
    $first->participants()->syncWithoutDetaching([$member->id]);

    $touching = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 18:00:00',
        'ends_at' => '2026-10-10 20:00:00',
    ]);
    $later = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 19:00:00',
        'ends_at' => '2026-10-10 21:00:00',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $touching])
        ->assertSee('Batas Sentuh Unik');

    Livewire::test('pages::admin.session-detail', ['session' => $later])
        ->assertSee('Batas Sentuh Unik');
});

test('cancelled and finished sessions do not block members', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->anggota()->create(['name' => 'Bebas Batal Unik']);

    $cancelled = AttendanceSession::factory()->cancelled()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);
    $cancelled->participants()->syncWithoutDetaching([$member->id]);

    $finished = AttendanceSession::factory()->finished()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);
    $finished->participants()->syncWithoutDetaching([$member->id]);

    $target = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $target])
        ->assertSee('Bebas Batal Unik');
});

test('manual bulk add of busy member is rejected', function () {
    $admin = User::factory()->admin()->create();
    $busy = User::factory()->anggota()->create();

    $blocker = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);
    $blocker->participants()->syncWithoutDetaching([$busy->id]);

    $target = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 16:00:00',
        'ends_at' => '2026-10-10 19:00:00',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $target])
        ->set('selectedUsers', [$busy->id])
        ->call('addBulkParticipants');

    expect($target->participants()->where('user_id', $busy->id)->exists())->toBeFalse();
});

test('overlapsWith detects overlap and boundary touch', function () {
    $base = AttendanceSession::factory()->make([
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);

    $inside = AttendanceSession::factory()->make([
        'starts_at' => '2026-10-10 16:00:00',
        'ends_at' => '2026-10-10 17:00:00',
    ]);
    $touching = AttendanceSession::factory()->make([
        'starts_at' => '2026-10-10 18:00:00',
        'ends_at' => '2026-10-10 20:00:00',
    ]);
    $outside = AttendanceSession::factory()->make([
        'starts_at' => '2026-10-10 19:00:00',
        'ends_at' => '2026-10-10 21:00:00',
    ]);

    expect($base->overlapsWith($inside))->toBeTrue();
    expect($base->overlapsWith($touching))->toBeFalse();
    expect($base->overlapsWith($outside))->toBeFalse();
});
