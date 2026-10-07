<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions
        $manageRoles = Permission::firstOrCreate(
            ['name' => 'manage-roles'],
            [
                'label' => 'Kelola Role',
                'description' => 'Hak akses khusus admin utama/superadmin untuk memberikan dan mengubah role pengguna.',
            ]
        );

        $manageUsers = Permission::firstOrCreate(
            ['name' => 'manage-users'],
            [
                'label' => 'Kelola Pengguna',
                'description' => 'Hak akses untuk melihat dan mengelola daftar pengguna.',
            ]
        );

        $createUsers = Permission::firstOrCreate(
            ['name' => 'create-users'],
            [
                'label' => 'Daftarkan Pengguna',
                'description' => 'Hak akses untuk mendaftarkan pengguna baru dengan role anggota.',
            ]
        );

        $manageSessions = Permission::firstOrCreate(
            ['name' => 'manage-sessions'],
            [
                'label' => 'Kelola Sesi',
                'description' => 'Hak akses untuk membuat, mengubah, memulai, menyelesaikan, dan membatalkan sesi olahraga.',
            ]
        );

        $manageSports = Permission::firstOrCreate(
            ['name' => 'manage-sports'],
            [
                'label' => 'Kelola Cabor',
                'description' => 'Hak akses khusus superadmin untuk mengelola cabang olahraga dan lapangan.',
            ]
        );

        // Define roles
        $superadmin = Role::firstOrCreate(
            ['name' => 'superadmin'],
            [
                'label' => 'Super Admin',
                'description' => 'Admin utama dengan akses penuh ke seluruh sistem dan pengaturan role.',
            ]
        );

        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'label' => 'Admin',
                'description' => 'Admin yang dapat mendaftarkan pengguna baru dengan role anggota.',
            ]
        );

        $anggota = Role::firstOrCreate(
            ['name' => 'anggota'],
            [
                'label' => 'Anggota',
                'description' => 'Pengguna biasa / anggota organisasi.',
            ]
        );

        // Sync permissions
        $superadmin->permissions()->sync([
            $manageRoles->id,
            $manageUsers->id,
            $createUsers->id,
            $manageSessions->id,
            $manageSports->id,
        ]);

        $admin->permissions()->sync([
            $manageUsers->id,
            $createUsers->id,
            $manageSessions->id,
        ]);

        $anggota->permissions()->sync([]);
    }
}
