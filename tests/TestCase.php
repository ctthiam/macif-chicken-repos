<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

/**
 * TestCase de base MACIF CHICKEN.
 *
 * Problème résolu : RefreshDatabase vide la DB avant chaque test,
 * donc les rôles Spatie créés par le seeder sont supprimés.
 * Solution : recréer les rôles ici dans setUp() avant chaque test.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Recrée les rôles Spatie après chaque RefreshDatabase.
     * Appelé automatiquement avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    /**
     * Crée les 3 rôles Spatie nécessaires si absents.
     * Vide le cache Spatie pour éviter les faux positifs entre tests.
     */
    protected function seedRoles(): void
    {
        // Vider le cache Spatie (important après RefreshDatabase)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = ['admin', 'eleveur', 'acheteur'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name'       => $role,
                'guard_name' => 'web',
            ]);
        }
    }
}