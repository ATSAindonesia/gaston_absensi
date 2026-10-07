<?php

use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\MemberCode;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('public registration assigns sequential member code', function () {
    $this->post(route('register.store'), [
        'name' => 'Member Satu',
        'email' => 'satu@example.com',
        'password' => 'password123!',
        'password_confirmation' => 'password123!',
    ]);

    $this->post(route('logout'));

    $this->post(route('register.store'), [
        'name' => 'Member Dua',
        'email' => 'dua@example.com',
        'password' => 'password123!',
        'password_confirmation' => 'password123!',
    ]);

    expect(User::where('email', 'satu@example.com')->first()->member_code)->toBe('GSTN-000001');
    expect(User::where('email', 'dua@example.com')->first()->member_code)->toBe('GSTN-000002');
});

test('member code format is GSTN followed by six digits', function () {
    $user = User::factory()->create();

    expect($user->member_code)->toMatch('/^GSTN-\d{6}$/');
});

test('admin registration assigns a member code', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->set('name', 'Budi Santoso')
        ->set('email', 'budi@example.com')
        ->set('password', 'password123!')
        ->set('password_confirmation', 'password123!')
        ->call('registerUser')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'budi@example.com')->first();

    expect($newUser->member_code)->toMatch('/^GSTN-\d{6}$/');
});

test('member codes are unique across many users', function () {
    $users = User::factory()->count(10)->create();
    $codes = $users->pluck('member_code');

    expect($codes->unique()->count())->toBe(10);
    expect($codes->filter(fn ($code) => ! preg_match('/^GSTN-\d{6}$/', (string) $code)))->toBeEmpty();
});

test('generator continues from the highest existing code', function () {
    User::factory()->create(['member_code' => 'GSTN-000042']);

    expect(MemberCode::generate())->toBe('GSTN-000043');
});

test('member code is shown on admin users page', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->anggota()->create();

    $response = $this->actingAs($admin)->get(route('admin.users'));

    $response->assertOk();
    $response->assertSee($member->member_code);
});

test('member code is shown on session detail participants table', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $response = $this->actingAs($admin)->get(route('admin.sessions.show', $session));

    $response->assertOk();
    $response->assertSee($member->member_code);
});

test('profile page shows own member code', function () {
    $user = User::factory()->anggota()->create();

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk();
    $response->assertSee($user->member_code);
});

test('session detail shows participant code and name but no participant email', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $member = User::factory()->anggota()->create(['email' => 'rahasia-unik@example.com']);
    $session->participants()->syncWithoutDetaching([$member->id]);

    $response = $this->actingAs($admin)->get(route('admin.sessions.show', $session));

    $response->assertOk();
    $response->assertSee($member->member_code);
    $response->assertSee($member->name);
    $response->assertDontSee('rahasia-unik@example.com');
});

test('participant search finds by code but not by email', function () {
    $admin = User::factory()->admin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $admin->id]);
    $member = User::factory()->anggota()->create();
    $session->participants()->syncWithoutDetaching([$member->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->set('participantSearch', $member->member_code)
        ->assertSee($member->name);

    Livewire::test('pages::admin.session-detail', ['session' => $session])
        ->set('participantSearch', $member->email)
        ->assertDontSee($member->name);
});

test('admin users page still shows email', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->anggota()->create();

    $response = $this->actingAs($admin)->get(route('admin.users'));

    $response->assertOk();
    $response->assertSee($member->email);
    $response->assertSee($member->member_code);
});
