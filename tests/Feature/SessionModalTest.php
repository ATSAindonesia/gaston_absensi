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
    $this->court = Court::factory()->for($this->sport)->create(['name' => 'Lapangan A']);
    $this->sportPadel = Sport::factory()->create(['name' => 'Padel']);
    $this->courtPadel = Court::factory()->for($this->sportPadel)->create(['name' => 'Lapangan B']);
});

test('session form modal closes after successful create', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('name', 'Latihan Pagi')
        ->set('sport_id', $this->sport->id)
        ->set('court_id', $this->court->id)
        ->set('session_date', '2026-10-10')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->call('saveSession')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'session-form');
});

test('session form modal stays open when create is blocked', function () {
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
        ->call('saveSession')
        ->assertNotDispatched('modal-close');
});

test('cancel confirmation modal closes after cancel', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->active()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('openCancel', $session->id)
        ->set('cancel_reason', 'Hujan')
        ->call('cancelSession')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'session-cancel');
});

test('delete confirmation modal closes after delete', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('confirmDelete', $session->id)
        ->call('deleteSession')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'session-delete');
});

test('detail modals close after remove and cancel', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->active()->create(['created_by' => $admin->id]);
    $member = User::factory()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->call('confirmRemove', $member->id)
        ->call('removeParticipant')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'participant-remove');

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->set('cancel_reason', 'Batal')
        ->call('cancelSession')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'detail-cancel');
});

test('time picker writes picked hour and minute to start field', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->call('openTimePicker', 'start')
        ->assertSet('pickerTarget', 'start')
        ->assertDispatched('modal-show', name: 'time-picker')
        ->set('pickerHour', '10')
        ->set('pickerMinute', '30')
        ->call('confirmTimePicker')
        ->assertSet('start_time', '10:30')
        ->assertDispatched('modal-close', name: 'time-picker');
});

test('time picker initializes from existing end time', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.sessions')
        ->set('end_time', '14:45')
        ->call('openTimePicker', 'end')
        ->assertSet('pickerHour', '14')
        ->assertSet('pickerMinute', '45')
        ->call('confirmTimePicker')
        ->assertSet('end_time', '14:45');
});

test('register user modal closes after successful registration', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->set('name', 'Citra Lestari')
        ->set('email', 'citra@example.com')
        ->set('password', 'password123!')
        ->set('password_confirmation', 'password123!')
        ->call('registerUser')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'register-user-modal');
});
