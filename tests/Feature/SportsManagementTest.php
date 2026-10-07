<?php

use App\Models\AttendanceSession;
use App\Models\Court;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('admin cannot access sports management page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.sports'))->assertForbidden();
});

test('superadmin can access sports management page', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)->get(route('admin.sports'))->assertOk();
});

test('superadmin can create sport and courts', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.sports')
        ->set('sportName', 'Tenis')
        ->call('saveSport')
        ->assertHasNoErrors();

    $sport = Sport::where('name', 'Tenis')->first();
    expect($sport)->not->toBeNull();

    Livewire::test('pages::admin.sports')
        ->set('courtNames.'.$sport->id, 'Lapangan 1')
        ->call('addCourt', $sport->id)
        ->assertHasNoErrors();

    expect(Court::where('sport_id', $sport->id)->where('name', 'Lapangan 1')->exists())->toBeTrue();
});

test('sport name must be unique', function () {
    $superadmin = User::factory()->superadmin()->create();
    Sport::factory()->create(['name' => 'Badminton']);

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.sports')
        ->set('sportName', 'Badminton')
        ->call('saveSport')
        ->assertHasErrors(['sportName']);
});

test('court name must be unique per sport', function () {
    $superadmin = User::factory()->superadmin()->create();
    $sport = Sport::factory()->create(['name' => 'Badminton']);
    Court::factory()->for($sport)->create(['name' => 'Lapangan A']);
    $other = Sport::factory()->create(['name' => 'Padel']);

    $this->actingAs($superadmin);

    // Same name in same sport is rejected
    Livewire::test('pages::admin.sports')
        ->set('courtNames.'.$sport->id, 'Lapangan A')
        ->call('addCourt', $sport->id)
        ->assertHasErrors(["courtNames.{$sport->id}"]);

    // Same name in another sport is allowed
    Livewire::test('pages::admin.sports')
        ->set('courtNames.'.$other->id, 'Lapangan A')
        ->call('addCourt', $other->id)
        ->assertHasNoErrors();
});

test('sport in use cannot be deleted', function () {
    $superadmin = User::factory()->superadmin()->create();
    $sport = Sport::factory()->create();
    AttendanceSession::factory()->create(['sport_id' => $sport->id]);

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.sports')
        ->call('confirmDeleteSport', $sport->id)
        ->call('deleteSport');

    expect(Sport::where('id', $sport->id)->exists())->toBeTrue();
});

test('court in use cannot be deleted', function () {
    $superadmin = User::factory()->superadmin()->create();
    $sport = Sport::factory()->create();
    $court = Court::factory()->for($sport)->create();
    AttendanceSession::factory()->create(['sport_id' => $sport->id, 'court_id' => $court->id]);

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.sports')
        ->call('confirmDeleteCourt', $court->id)
        ->call('deleteCourt');

    expect(Court::where('id', $court->id)->exists())->toBeTrue();
});

test('unused sport and court can be deleted', function () {
    $superadmin = User::factory()->superadmin()->create();
    $sport = Sport::factory()->create();
    $court = Court::factory()->for($sport)->create();

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.sports')
        ->call('confirmDeleteCourt', $court->id)
        ->call('deleteCourt')
        ->assertHasNoErrors();

    expect(Court::where('id', $court->id)->exists())->toBeFalse();

    Livewire::test('pages::admin.sports')
        ->call('confirmDeleteSport', $sport->id)
        ->call('deleteSport')
        ->assertHasNoErrors();

    expect(Sport::where('id', $sport->id)->exists())->toBeFalse();
});

test('session courts are filtered by selected sport', function () {
    $admin = User::factory()->admin()->create();
    $badminton = Sport::factory()->create(['name' => 'Badminton']);
    $padel = Sport::factory()->create(['name' => 'Padel']);
    Court::factory()->for($badminton)->create(['name' => 'Lap Bulutangkis']);
    Court::factory()->for($padel)->create(['name' => 'Lap Padel']);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('sport_id', $badminton->id)
        ->assertSee('Lap Bulutangkis')
        ->assertDontSee('Lap Padel');
});

test('session rejects court from another sport', function () {
    $admin = User::factory()->admin()->create();
    $badminton = Sport::factory()->create(['name' => 'Badminton']);
    $padel = Sport::factory()->create(['name' => 'Padel']);
    $padelCourt = Court::factory()->for($padel)->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Silang Cabor')
        ->set('sport_id', $badminton->id)
        ->set('court_id', $padelCourt->id)
        ->set('session_date', '2026-10-10')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession')
        ->assertHasErrors(['court_id']);

    expect(AttendanceSession::where('name', 'Silang Cabor')->exists())->toBeFalse();
});
