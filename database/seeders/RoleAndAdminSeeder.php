<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleAndAdminSeeder extends Seeder
{
    /**
     * Crée les 3 rôles Spatie et le compte admin par défaut.
     */
    public function run(): void
    {
        // Reset cached roles & permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Créer les rôles ─────────────────────────────────
        $roles = ['admin', 'eleveur', 'acheteur'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // ─── Créer le compte admin par défaut ────────────────
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@macifchicken.sn')],
            [
                'name'        => 'Super Admin',
                'password'    => Hash::make(env('ADMIN_PASSWORD', 'Admin@2026!')),
                'phone'       => env('ADMIN_PHONE', '+221700000000'),
                'role'        => 'admin',
                'is_verified' => true,
                'is_active'   => true,
            ]
        );

        $admin->assignRole('admin');

        $this->command->info('✅ Rôles créés : admin, eleveur, acheteur');
        $this->command->info('✅ Compte admin créé : ' . $admin->email);
    }
}