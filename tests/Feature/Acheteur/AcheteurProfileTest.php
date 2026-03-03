<?php

namespace Tests\Feature\Acheteur;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   PRO-04 — Récupérer profil acheteur (GET /api/acheteur/profile)
 *   PRO-05 — Modifier profil acheteur  (PUT /api/acheteur/profile)
 *
 * Fichier : tests/Feature/Acheteur/AcheteurProfileTest.php
 * Lancer  : php artisan test --filter=AcheteurProfileTest
 */
class AcheteurProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAcheteur(string $type = 'restaurant'): User
    {
        $user = User::factory()->acheteur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $user->acheteurProfile()->create([
            'type'              => $type,
            'nom_etablissement' => 'Restaurant Le Baobab',
            'ninea'             => null,
        ]);

        Sanctum::actingAs($user);

        return $user->load('acheteurProfile');
    }

    // ══════════════════════════════════════════════════════════════
    // PRO-04 — GET /api/acheteur/profile
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_acheteur_profile(): void
    {
        $user = $this->actingAsAcheteur();

        $response = $this->getJson('/api/acheteur/profile');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'email', 'phone', 'role',
                    'acheteur_profile' => ['id', 'type', 'nom_etablissement', 'ninea'],
                ],
            ]);

        $this->assertEquals($user->id, $response->json('data.id'));
        $this->assertEquals('restaurant', $response->json('data.acheteur_profile.type'));
    }

    #[Test]
    public function it_returns_401_when_not_authenticated(): void
    {
        $this->getJson('/api/acheteur/profile')->assertStatus(401);
    }

    #[Test]
    public function it_returns_403_when_eleveur_accesses_acheteur_profile(): void
    {
        $eleveur = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        Sanctum::actingAs($eleveur);

        $this->getJson('/api/acheteur/profile')->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // PRO-05 — PUT /api/acheteur/profile
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_updates_type_successfully(): void
    {
        $user = $this->actingAsAcheteur('restaurant');

        $response = $this->putJson('/api/acheteur/profile', ['type' => 'cantine']);

        $response->assertStatus(200)
            ->assertJsonPath('data.acheteur_profile.type', 'cantine');

        $this->assertDatabaseHas('acheteur_profiles', ['user_id' => $user->id, 'type' => 'cantine']);
    }

    #[Test]
    public function it_updates_nom_etablissement(): void
    {
        $user = $this->actingAsAcheteur();

        $response = $this->putJson('/api/acheteur/profile', [
            'nom_etablissement' => 'Hotel Teranga Dakar',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.acheteur_profile.nom_etablissement', 'Hotel Teranga Dakar');
    }

    #[Test]
    public function it_updates_ninea(): void
    {
        $user = $this->actingAsAcheteur();

        $response = $this->putJson('/api/acheteur/profile', ['ninea' => '123456789AB1']);

        $response->assertStatus(200)
            ->assertJsonPath('data.acheteur_profile.ninea', '123456789AB1');
    }

    #[Test]
    public function it_rejects_invalid_ninea_format(): void
    {
        $this->actingAsAcheteur();

        $this->putJson('/api/acheteur/profile', ['ninea' => 'INVALID'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ninea']);
    }

    #[Test]
    public function it_rejects_invalid_type(): void
    {
        $this->actingAsAcheteur();

        $this->putJson('/api/acheteur/profile', ['type' => 'supermarche'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function it_updates_ville_and_adresse_on_user(): void
    {
        $user = $this->actingAsAcheteur();

        $this->putJson('/api/acheteur/profile', [
            'ville'   => 'Saint-Louis',
            'adresse' => 'Rue General de Gaulle',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'      => $user->id,
            'ville'   => 'Saint-Louis',
            'adresse' => 'Rue General de Gaulle',
        ]);
    }

    #[Test]
    public function it_can_update_multiple_fields_at_once(): void
    {
        $this->actingAsAcheteur();

        $this->putJson('/api/acheteur/profile', [
            'type'              => 'hotel',
            'nom_etablissement' => 'Hotel Savana',
            'ville'             => 'Ziguinchor',
        ])->assertStatus(200)
            ->assertJsonPath('data.acheteur_profile.type', 'hotel')
            ->assertJsonPath('data.acheteur_profile.nom_etablissement', 'Hotel Savana')
            ->assertJsonPath('data.ville', 'Ziguinchor');
    }

    #[Test]
    public function it_can_set_nom_etablissement_to_null(): void
    {
        $user = $this->actingAsAcheteur();

        $this->putJson('/api/acheteur/profile', ['nom_etablissement' => null])
            ->assertStatus(200);

        $this->assertDatabaseHas('acheteur_profiles', [
            'user_id'           => $user->id,
            'nom_etablissement' => null,
        ]);
    }

    #[Test]
    public function it_does_nothing_when_no_fields_sent(): void
    {
        $user = $this->actingAsAcheteur();

        $this->putJson('/api/acheteur/profile', [])
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.acheteur_profile.type', $user->acheteurProfile->type);
    }

    #[Test]
    public function it_returns_403_for_eleveur_on_update(): void
    {
        $eleveur = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        Sanctum::actingAs($eleveur);

        $this->putJson('/api/acheteur/profile', ['type' => 'cantine'])->assertStatus(403);
    }

    #[Test]
    public function it_requires_authentication_for_update(): void
    {
        $this->putJson('/api/acheteur/profile', ['type' => 'cantine'])->assertStatus(401);
    }

    #[Test]
    public function it_accepts_all_valid_types(): void
    {
        foreach (['restaurant', 'cantine', 'hotel', 'traiteur', 'particulier'] as $type) {
            $this->actingAsAcheteur();

            $this->putJson('/api/acheteur/profile', ['type' => $type])
                ->assertStatus(200)
                ->assertJsonPath('data.acheteur_profile.type', $type);
        }
    }
}