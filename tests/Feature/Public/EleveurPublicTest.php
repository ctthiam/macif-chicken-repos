<?php

namespace Tests\Feature\Public;

use App\Models\Avis;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature : PRO-06 — Page publique éleveur
 * GET /api/eleveurs/{id}/public
 *
 * Route publique — pas d'authentification requise.
 *
 * Fichier : tests/Feature/Public/EleveurPublicTest.php
 * Lancer  : php artisan test --filter=EleveurPublicTest
 */
class EleveurPublicTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper : crée un éleveur complet avec profil ────────────

    private function createEleveur(array $overrides = []): User
    {
        $eleveur = User::factory()->eleveur()->create(array_merge([
            'is_verified' => true,
            'is_active'   => true,
        ], $overrides));

        $eleveur->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Soleil Levant',
            'description'    => 'Spécialiste poulets fermiers',
            'localisation'   => 'Route de Rufisque',
            'latitude'       => 14.716,
            'longitude'      => -17.467,
            'is_certified'   => false,
            'note_moyenne'   => 4.5,
            'nombre_avis'    => 3,
            'photos'         => [],
        ]);

        return $eleveur;
    }

    // ══════════════════════════════════════════════════════════════
    // Tests profil de base
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_public_eleveur_profile(): void
    {
        $eleveur = $this->createEleveur();

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'avatar', 'ville', 'created_at',
                    'eleveur_profile' => [
                        'nom_poulailler', 'description', 'localisation',
                        'latitude', 'longitude', 'is_certified',
                        'note_moyenne', 'nombre_avis', 'photos',
                    ],
                    'stocks',
                    'avis',
                    'stats' => ['total_stocks', 'total_avis'],
                ],
            ]);

        $this->assertEquals($eleveur->id, $response->json('data.id'));
        $this->assertEquals('Ferme Soleil Levant', $response->json('data.eleveur_profile.nom_poulailler'));
    }

    #[Test]
    public function it_does_not_expose_sensitive_fields(): void
    {
        $eleveur = $this->createEleveur();

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        $data = $response->json('data');

        // Ces champs NE doivent PAS apparaître dans une réponse publique
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('phone', $data);
        $this->assertArrayNotHasKey('adresse', $data);
        $this->assertArrayNotHasKey('is_active', $data);
        $this->assertArrayNotHasKey('is_verified', $data);
        $this->assertArrayNotHasKey('password', $data);
    }

    #[Test]
    public function it_returns_404_for_unknown_eleveur(): void
    {
        $this->getJson('/api/eleveurs/9999/public')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Éleveur introuvable.']);
    }

    #[Test]
    public function it_returns_404_for_inactive_eleveur(): void
    {
        $eleveur = $this->createEleveur(['is_active' => false]);

        $this->getJson("/api/eleveurs/{$eleveur->id}/public")
            ->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_when_id_belongs_to_acheteur(): void
    {
        $acheteur = User::factory()->acheteur()->create(['is_active' => true]);

        $this->getJson("/api/eleveurs/{$acheteur->id}/public")
            ->assertStatus(404);
    }

    #[Test]
    public function it_is_accessible_without_authentication(): void
    {
        $eleveur = $this->createEleveur();

        // Pas de Sanctum::actingAs — accès anonyme
        $this->getJson("/api/eleveurs/{$eleveur->id}/public")
            ->assertStatus(200);
    }

    // ══════════════════════════════════════════════════════════════
    // Tests stocks
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_only_disponible_stocks(): void
    {
        $eleveur = $this->createEleveur();

        // 2 stocks disponibles
        Stock::factory()->count(2)->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        // 1 stock épuisé — ne doit pas apparaître
        Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'epuise',
        ]);

        // 1 stock expiré — ne doit pas apparaître
        Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'expire',
        ]);

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        $response->assertStatus(200);
        $stocks = $response->json('data.stocks');

        $this->assertCount(2, $stocks);
        foreach ($stocks as $stock) {
            $this->assertArrayHasKey('titre', $stock);
            $this->assertArrayHasKey('prix_par_kg', $stock);
        }
    }

    #[Test]
    public function it_returns_empty_stocks_when_none_available(): void
    {
        $eleveur = $this->createEleveur();

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.stocks'));
        $this->assertEquals(0, $response->json('data.stats.total_stocks'));
    }

    #[Test]
    public function it_increments_vues_on_stocks(): void
    {
        $eleveur = $this->createEleveur();

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
            'vues'       => 5,
        ]);

        $this->getJson("/api/eleveurs/{$eleveur->id}/public")
            ->assertStatus(200);

        // Les vues doivent avoir augmenté
        $this->assertGreaterThan(5, $stock->fresh()->vues);
    }

    // ══════════════════════════════════════════════════════════════
    // Tests avis
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_avis_for_eleveur(): void
    {
        $eleveur  = $this->createEleveur();
        $acheteur = User::factory()->acheteur()->create();

        Avis::factory()->count(3)->create([
            'cible_id'   => $eleveur->id,
            'auteur_id'  => $acheteur->id,
            'is_reported' => false,
        ]);

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        $response->assertStatus(200);
        $avis = $response->json('data.avis');

        $this->assertCount(3, $avis);
        $this->assertEquals(3, $response->json('data.stats.total_avis'));

        // Vérifier la structure d'un avis
        $this->assertArrayHasKey('note', $avis[0]);
        $this->assertArrayHasKey('commentaire', $avis[0]);
        $this->assertArrayHasKey('auteur', $avis[0]);
    }

    #[Test]
    public function it_excludes_reported_avis(): void
    {
        $eleveur  = $this->createEleveur();
        $acheteur = User::factory()->acheteur()->create();

        // 2 avis normaux + 1 signalé
        Avis::factory()->count(2)->create([
            'cible_id'    => $eleveur->id,
            'auteur_id'   => $acheteur->id,
            'is_reported' => false,
        ]);

        Avis::factory()->create([
            'cible_id'    => $eleveur->id,
            'auteur_id'   => $acheteur->id,
            'is_reported' => true,
        ]);

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        // Seulement 2 avis non signalés
        $this->assertCount(2, $response->json('data.avis'));
    }

    #[Test]
    public function it_limits_avis_to_10(): void
    {
        $eleveur  = $this->createEleveur();
        $acheteur = User::factory()->acheteur()->create();

        // Créer 15 avis
        Avis::factory()->count(15)->create([
            'cible_id'    => $eleveur->id,
            'auteur_id'   => $acheteur->id,
            'is_reported' => false,
        ]);

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        // Max 10 retournés
        $this->assertCount(10, $response->json('data.avis'));
    }

    #[Test]
    public function it_does_not_expose_auteur_email_in_avis(): void
    {
        $eleveur  = $this->createEleveur();
        $acheteur = User::factory()->acheteur()->create();

        Avis::factory()->create([
            'cible_id'    => $eleveur->id,
            'auteur_id'   => $acheteur->id,
            'is_reported' => false,
        ]);

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        $auteur = $response->json('data.avis.0.auteur');

        // Seulement name + avatar — pas d'email
        $this->assertArrayHasKey('name', $auteur);
        $this->assertArrayHasKey('avatar', $auteur);
        $this->assertArrayNotHasKey('email', $auteur);
        $this->assertArrayNotHasKey('phone', $auteur);
    }
}