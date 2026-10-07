<?php

use App\Models\AttendanceSession;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('anggota cannot access admin dashboard', function () {
    $anggota = User::factory()->anggota()->create();

    $this->actingAs($anggota)->get(route('admin.dashboard'))->assertForbidden();
});

test('admin and superadmin can access admin dashboard', function () {
    $admin = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($superadmin)->get(route('admin.dashboard'))->assertOk();
});

test('admin is redirected to admin dashboard after login', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
    $this->assertAuthenticated();
});

test('anggota is redirected to member dashboard after login', function () {
    $anggota = User::factory()->anggota()->create();

    $response = $this->post(route('login.store'), [
        'email' => $anggota->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('member.dashboard', absolute: false));
    $this->assertAuthenticated();
});

test('admin dashboard shows stats and active session', function () {
    $admin = User::factory()->admin()->create();
    $active = AttendanceSession::factory()->active()->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard Admin');
    $response->assertSee('Total Sesi');
    $response->assertSee('Total Pengguna');
    $response->assertSee('/logo_gaston.jpeg', false);
    $response->assertSee($active->name);
    $response->assertSee('Kelola Sesi');
    $response->assertSee('Informasi Pengguna');
});

test('admin pages use admin sidebar without member dashboard menu', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.sessions'));

    $response->assertOk();
    $response->assertSee('Dashboard Admin');
    $response->assertSee(route('admin.dashboard', absolute: false));
});

test('admin and superadmin are redirected from member dashboard to admin area', function () {
    $admin = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($admin)->get(route('member.dashboard'))->assertRedirect(route('admin.dashboard'));
    $this->actingAs($superadmin)->get(route('member.dashboard'))->assertRedirect(route('admin.dashboard'));
});

test('role settings page uses admin sidebar', function () {
    $superadmin = User::factory()->superadmin()->create();

    $response = $this->actingAs($superadmin)->get(route('admin.roles'));

    $response->assertOk();
    $response->assertSee('Dashboard Admin');
    $response->assertDontSee('Platform');
});

test('admin settings live under admin scope', function () {
    $admin = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();
    $anggota = User::factory()->anggota()->create();

    $this->actingAs($admin)->get(route('admin.settings.profile'))->assertOk();
    $this->actingAs($superadmin)->get(route('admin.settings.profile'))->assertOk();
    $this->actingAs($anggota)->get(route('admin.settings.profile'))->assertForbidden();

    $response = $this->actingAs($admin)->get(route('admin.settings.profile'));
    $response->assertSee(route('admin.settings.security', absolute: false));
    $response->assertSee(route('admin.settings.appearance', absolute: false));
    $response->assertSee('Dashboard Admin');
    $response->assertDontSee('Platform');
});

test('member dashboard does not show admin menu', function () {
    $anggota = User::factory()->anggota()->create();

    $response = $this->actingAs($anggota)->get(route('member.dashboard'));

    $response->assertSee('Mulai Kamera');

    $response->assertOk();
    $response->assertDontSee('Kelola Sesi');
    $response->assertDontSee('Dashboard Admin');
});
