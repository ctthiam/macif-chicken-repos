<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature : PRO-07 — Badge Éleveur Certifié
 * PUT /api/admin/users/{id}/certifier
 *
 * Réservé à l'admin uniquement.
 *
 * Fichier : tests/Feature/Admin/CertifierEleveurTest.php
 * Lancer  : php artisan test --filter=CertifierEleveurTest
 */
class CertifierEleveurTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);
        Sanctum::actingAs($admin);
        return $admin;
    }

    private function createEleveurWithProfile(bool $isCertified = false): User
    {
        $eleveur = User::factory()->eleveur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $eleveur->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test',
            'description'    => 'Test',
            'localisation'   => 'Dakar',
            'is_certified'   => $isCertified,
            'note_moyenne'   => 0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ]);

        return $eleveur;
    }

    // ══════════════════════════════════════════════════════════════
    // Tests certification
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_certifies_an_eleveur(): void
    {
        $this->actingAsAdmin();
        $eleveur = $this->createEleveurWithProfile(isCertified: false);

        $response = $this->putJson("/api/admin/users/{$eleveur->id}/certifier");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.eleveur_profile.is_certified', true);

        $this->assertDatabaseHas('eleveur_profiles', [
            'user_id'      => $eleveur->id,
            'is_certified' => true,
        ]);
    }

    #[Test]
    public function it_removes_certification_from_eleveur(): void
    {
        $this->actingAsAdmin();
        $eleveur = $this->createEleveurWithProfile(isCertified: true);

        $response = $this->putJson("/api/admin/users/{$eleveur->id}/certifier");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.eleveur_profile.is_certified', false);

        $this->assertDatabaseHas('eleveur_profiles', [
            'user_id'      => $eleveur->id,
            'is_certified' => false,
        ]);
    }

    #[Test]
    public function it_toggles_certification_on_multiple_calls(): void
    {
        $this->actingAsAdmin();
        $eleveur = $this->createEleveurWithProfile(isCertified: false);

        // Appel 1 : certifie
        $this->putJson("/api/admin/users/{$eleveur->id}/certifier")
            ->assertJsonPath('data.eleveur_profile.is_certified', true);

        // Appel 2 : retire
        $this->putJson("/api/admin/users/{$eleveur->id}/certifier")
            ->assertJsonPath('data.eleveur_profile.is_certified', false);

        // Appel 3 : recertifie
        $this->putJson("/api/admin/users/{$eleveur->id}/certifier")
            ->assertJsonPath('data.eleveur_profile.is_certified', true);
    }

    #[Test]
    public function it_returns_message_when_certifying(): void
    {
        $this->actingAsAdmin();
        $eleveur = $this->createEleveurWithProfile(isCertified: false);

        $response = $this->putJson("/api/admin/users/{$eleveur->id}/certifier");

        $response->assertStatus(200);
        $this->assertStringContainsString('certifié', $response->json('message'));
    }

    #[Test]
    public function it_returns_message_when_removing_certification(): void
    {
        $this->actingAsAdmin();
        $eleveur = $this->createEleveurWithProfile(isCertified: true);

        $response = $this->putJson("/api/admin/users/{$eleveur->id}/certifier");

        $response->assertStatus(200);
        $this->assertStringContainsString('retirée', $response->json('message'));
    }

    // ══════════════════════════════════════════════════════════════
    // Tests erreurs métier
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_404_for_unknown_user(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/admin/users/9999/certifier')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Éleveur introuvable.']);
    }

    #[Test]
    public function it_returns_404_when_user_is_not_eleveur(): void
    {
        $this->actingAsAdmin();

        $acheteur = User::factory()->acheteur()->create(['is_verified' => true]);

        $this->putJson("/api/admin/users/{$acheteur->id}/certifier")
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_422_when_eleveur_has_no_profile(): void
    {
        $this->actingAsAdmin();

        // Éleveur sans profil poulailler
        $eleveur = User::factory()->eleveur()->create(['is_verified' => true]);

        $this->putJson("/api/admin/users/{$eleveur->id}/certifier")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ══════════════════════════════════════════════════════════════
    // Tests autorisation
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_403_for_eleveur_user(): void
    {
        $eleveur = User::factory()->eleveur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);
        Sanctum::actingAs($eleveur);

        $target = $this->createEleveurWithProfile();

        $this->putJson("/api/admin/users/{$target->id}/certifier")
            ->assertStatus(403);
    }

    #[Test]
    public function it_returns_403_for_acheteur_user(): void
    {
        $acheteur = User::factory()->acheteur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);
        Sanctum::actingAs($acheteur);

        $target = $this->createEleveurWithProfile();

        $this->putJson("/api/admin/users/{$target->id}/certifier")
            ->assertStatus(403);
    }

    #[Test]
    public function it_returns_401_when_not_authenticated(): void
    {
        $eleveur = $this->createEleveurWithProfile();

        $this->putJson("/api/admin/users/{$eleveur->id}/certifier")
            ->assertStatus(401);
    }
}