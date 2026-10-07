<?php

use App\Models\AttendanceSession;
use App\Models\Court;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->sport = Sport::factory()->create(['name' => 'Padel']);
    $this->court = Court::factory()->for($this->sport)->create(['name' => 'Lapangan Card']);
});

test('soon card shows title, metadata, primary start action, and overflow menu', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'name' => 'Card Refactor Unik',
        'sport_id' => $this->sport->id,
        'court_id' => $this->court->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.sessions'));

    $response->assertOk();
    // Header zone: semantic heading + contextual badges
    $response->assertSee('<h3', false);
    $response->assertSee('Card Refactor Unik');
    $response->assertSee('Room Saya');
    $response->assertSee($session->status->label());
    $response->assertSee('Padel');
    // Body zone: icon metadata labels and values
    $response->assertSee('Lapangan Card');
    $response->assertSee($admin->name);
    // Footer zone: primary + secondary + overflow menu
    $response->assertSee('Mulai');
    $response->assertSee('Ubah');
    $response->assertSee('Batalkan sesi');
    $response->assertSee('Hapus sesi');
});

test('active card shows finish action and overflow menu without delete', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->active()->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->get(route('admin.sessions'));

    $response->assertOk();
    $response->assertSee('Selesaikan');
    $response->assertSee('Batalkan sesi');
    $response->assertDontSee('Hapus sesi');
    $response->assertDontSee('>Mulai<', false);
});

test('read-only card shows view action without manage buttons or menu', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    AttendanceSession::factory()->create(['created_by' => $owner->id, 'name' => 'Room Orang Lain Unik']);

    $response = $this->actingAs($other)->get(route('admin.sessions'));

    $response->assertOk();
    $response->assertSee('Room Orang Lain Unik');
    $response->assertSee('Lihat');
    $response->assertSee('Read-only');
    $response->assertDontSee('Batalkan sesi');
    $response->assertDontSee('Hapus sesi');
    $response->assertDontSee('Opsi lain');
});

test('overflow menu actions open their confirmation modals', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('openCancel', $session->id)
        ->assertDispatched('modal-show', name: 'session-cancel');

    Livewire::test('pages::admin.sessions')
        ->call('confirmDelete', $session->id)
        ->assertDispatched('modal-show', name: 'session-delete');
});
