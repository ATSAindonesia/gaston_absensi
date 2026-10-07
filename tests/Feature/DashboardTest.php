<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('member.dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated members can visit the member dashboard', function () {
    $user = User::factory()->anggota()->create();
    $this->actingAs($user);

    $response = $this->get(route('member.dashboard'));
    $response->assertOk();
    $response->assertSee('Mulai Kamera');
});
