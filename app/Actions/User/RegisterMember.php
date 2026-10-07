<?php

namespace App\Actions\User;

use App\Models\User;

class RegisterMember
{
    /**
     * Register a new user as anggota (not admin).
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $user->assignRole('anggota');

        return $user->refresh();
    }
}
