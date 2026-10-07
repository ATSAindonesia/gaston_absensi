<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('public registration automatically assigns role anggota', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Member User',
        'email' => 'member@example.com',
        'password' => 'password123!',
        'password_confirmation' => 'password123!',
    ]);

    $response->assertSessionHasNoErrors();

    $user = User::where('email', 'member@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('anggota'))->toBeTrue();
    expect($user->isAnggota())->toBeTrue();
    expect($user->isAdmin())->toBeFalse();
    expect($user->isSuperAdmin())->toBeFalse();
});

test('anggota cannot access admin user management page', function () {
    $anggota = User::factory()->anggota()->create();

    $response = $this->actingAs($anggota)->get(route('admin.users'));

    $response->assertForbidden();
});

test('admin can access admin user management page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.users'));

    $response->assertOk();
});

test('superadmin can access admin user management page', function () {
    $superadmin = User::factory()->superadmin()->create();

    $response = $this->actingAs($superadmin)->get(route('admin.users'));

    $response->assertOk();
});

test('admin can register new user and newly registered user automatically has role anggota', function () {
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

    expect($newUser)->not->toBeNull();
    expect($newUser->hasRole('anggota'))->toBeTrue();
    expect($newUser->isAnggota())->toBeTrue();
    // Must NOT have admin or superadmin role
    expect($newUser->isAdmin())->toBeFalse();
    expect($newUser->isSuperAdmin())->toBeFalse();
});

test('admin cannot access special role settings page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.roles'));

    $response->assertForbidden();
});

test('anggota cannot access special role settings page', function () {
    $anggota = User::factory()->anggota()->create();

    $response = $this->actingAs($anggota)->get(route('admin.roles'));

    $response->assertForbidden();
});

test('superadmin can access special role settings page', function () {
    $superadmin = User::factory()->superadmin()->create();

    $response = $this->actingAs($superadmin)->get(route('admin.roles'));

    $response->assertOk();
});

test('only superadmin can grant admin role to a user through special settings', function () {
    $superadmin = User::factory()->superadmin()->create();
    $targetUser = User::factory()->anggota()->create();

    expect($targetUser->isAnggota())->toBeTrue();
    expect($targetUser->isAdmin())->toBeFalse();

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.roles')
        ->call('updateUserRole', $targetUser->id, 'admin')
        ->assertHasNoErrors();

    $targetUser->refresh();
    expect($targetUser->isAdmin())->toBeTrue();
    expect($targetUser->hasRole('admin'))->toBeTrue();
});

test('superadmin can demote admin back to anggota', function () {
    $superadmin = User::factory()->superadmin()->create();
    $targetUser = User::factory()->admin()->create();

    expect($targetUser->isAdmin())->toBeTrue();

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.roles')
        ->call('updateUserRole', $targetUser->id, 'anggota')
        ->assertHasNoErrors();

    $targetUser->refresh();
    expect($targetUser->isAnggota())->toBeTrue();
    expect($targetUser->isAdmin())->toBeFalse();
});

test('superadmin cannot demote their own account from superadmin', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin);

    Livewire::test('pages::admin.roles')
        ->call('updateUserRole', $superadmin->id, 'anggota')
        ->assertHasNoErrors();

    $superadmin->refresh();
    expect($superadmin->isSuperAdmin())->toBeTrue();
});
