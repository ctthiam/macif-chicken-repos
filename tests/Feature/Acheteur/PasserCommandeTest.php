<?php

namespace Tests\Feature\Acheteur;

use App\Models\Abonnement;
use App\Models\Commande;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   CMD-01 — Passer une commande     (POST /api/acheteur/commandes)
 *   CMD-02 — Calcul montant + 7%     (inclus CMD-01)
 *   CMD-09 — Historique acheteur     (GET  /api/acheteur/commandes)
 *   CMD-10 — Historique éleveur      (GET  /api/eleveur/commandes)
 *   CMD-11 — Détail commande         (GET  /api/acheteur/commandes/{id})
 *
 * Fichier : tests/Feature/Acheteur/PasserCommandeTest.php
 * Lancer  : php artisan test --filter=PasserCommandeTest
 */
class PasserCommandeTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function createEleveurWithStock(array $stockData = []): array
    {
        $eleveur = User::factory()->eleveur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);

        Abonnement::create([
            'eleveur_id'         => $eleveur->id,
            'plan'               => 'pro',
            'prix_mensuel'       => 15000,
            'date_debut'         => now()->toDateString(),
            'date_fin'           => now()->addMonth()->toDateString(),
            'statut'             => 'actif',
            'methode_paiement'   => 'wave',
            'reference_paiement' => 'REF-E-' . $eleveur->id,
        ]);

        $stock = Stock::factory()->create(array_merge([
            'eleveur_id'          => $eleveur->id,
            'statut'              => 'disponible',
            'quantite_disponible' => 50,
            'poids_moyen_kg'      => 2.5,
            'prix_par_kg'         => 2000,
            'mode_vente'          => 'vivant',
            'date_disponibilite'  => now()->toDateString(),
        ], $stockData));

        return [$eleveur, $stock];
    }

    private function actingAsAcheteur(): User
    {
        $acheteur = User::factory()->acheteur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);
        $acheteur->acheteurProfile()->create([
            'type'              => 'restaurant',
            'nom_etablissement' => 'Test Restaurant',
            'ninea'             => null,
        ]);
        Sanctum::actingAs($acheteur);
        return $acheteur;
    }

    private function validPayload(int $stockId, array $overrides = []): array
    {
        return array_merge([
            'stock_id'          => $stockId,
            'quantite'          => 5,
            'adresse_livraison' => 'Dakar, Plateau, Rue 12',
            'mode_paiement'     => 'wave',
        ], $overrides);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-01 — POST /api/acheteur/commandes
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_creates_a_commande_successfully(): void
    {
        $acheteur = $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock();

        $response = $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id));

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Commande passée avec succès.'])
            ->assertJsonStructure([
                'data' => [
                    'id', 'statut_commande', 'statut_paiement',
                    'quantite', 'poids_total', 'montant_total',
                    'commission_plateforme', 'montant_eleveur',
                    'adresse_livraison', 'mode_paiement',
                    'stock', 'eleveur', 'acheteur',
                ],
            ]);

        $this->assertDatabaseHas('commandes', [
            'acheteur_id'     => $acheteur->id,
            'eleveur_id'      => $eleveur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'confirmee',
            'statut_paiement' => 'en_attente',
        ]);
    }

    #[Test]
    public function it_sets_statut_confirmee_and_paiement_en_attente(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock();

        $response = $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.statut_commande', 'confirmee')
            ->assertJsonPath('data.statut_paiement', 'en_attente');
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-02 — Calcul automatique montant + commission 7%
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_calculates_montant_correctly(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock([
            'quantite_disponible' => 100,
            'poids_moyen_kg'      => 2.0,
            'prix_par_kg'         => 2500,
        ]);

        // 10 poulets × 2500 FCFA/kg × 2.0 kg = 50 000 FCFA
        $response = $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id, [
            'quantite' => 10,
        ]));

        $response->assertStatus(201);

        $data = $response->json('data');
        $this->assertEquals(20.0, (float) $data['poids_total']);    // 10 × 2.0
        $this->assertEquals(50000, (int) $data['montant_total']);    // 10 × 2500 × 2.0
        $this->assertEquals(3500, (int) $data['commission_plateforme']); // 50000 × 7%
        $this->assertEquals(46500, (int) $data['montant_eleveur']);  // 50000 - 3500
    }

    #[Test]
    public function it_calculates_commission_at_7_percent(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock([
            'poids_moyen_kg' => 2.0,
            'prix_par_kg'    => 1000,
        ]);

        // 1 × 1000 × 2.0 = 2000 FCFA → commission 7% = 140
        $response = $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id, [
            'quantite' => 1,
        ]));

        $response->assertStatus(201);
        $this->assertEquals(2000, (int) $response->json('data.montant_total'));
        $this->assertEquals(140, (int) $response->json('data.commission_plateforme'));
        $this->assertEquals(1860, (int) $response->json('data.montant_eleveur'));
    }

    // ══════════════════════════════════════════════════════════════
    // STK-05 — Statut stock auto après commande
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sets_stock_to_reserve_after_commande(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock(['quantite_disponible' => 50]);

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id, ['quantite' => 10]));

        $this->assertDatabaseHas('stocks', [
            'id'                  => $stock->id,
            'statut'              => 'reserve',
            'quantite_disponible' => 40,
        ]);
    }

    #[Test]
    public function it_sets_stock_to_epuise_when_quantite_reaches_zero(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock(['quantite_disponible' => 5]);

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id, ['quantite' => 5]));

        $this->assertDatabaseHas('stocks', [
            'id'                  => $stock->id,
            'statut'              => 'epuise',
            'quantite_disponible' => 0,
        ]);
    }

    #[Test]
    public function it_decrements_quantite_disponible(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock(['quantite_disponible' => 20]);

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id, ['quantite' => 7]));

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantite_disponible' => 13]);
    }

    // ══════════════════════════════════════════════════════════════
    // Tests règles métier
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_rejects_when_stock_not_disponible(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock(['statut' => 'epuise']);

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id))
            ->assertStatus(409)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_quantity_exceeding_stock(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock(['quantite_disponible' => 3]);

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id, ['quantite' => 10]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantite']);
    }

    #[Test]
    public function it_prevents_eleveur_from_ordering_own_stock(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock();
        Sanctum::actingAs($eleveur);

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id))
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_invalid_mode_paiement(): void
    {
        $this->actingAsAcheteur();
        [$eleveur, $stock] = $this->createEleveurWithStock();

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id, [
            'mode_paiement' => 'bitcoin',
        ]))->assertStatus(422)->assertJsonValidationErrors(['mode_paiement']);
    }

    #[Test]
    public function it_rejects_missing_required_fields(): void
    {
        $this->actingAsAcheteur();

        $this->postJson('/api/acheteur/commandes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock_id', 'quantite', 'adresse_livraison', 'mode_paiement']);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock();

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id))
            ->assertStatus(401);
    }

    #[Test]
    public function it_returns_403_for_eleveur_role(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock();
        Sanctum::actingAs($eleveur);

        $this->postJson('/api/acheteur/commandes', $this->validPayload($stock->id))
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-09 — GET /api/acheteur/commandes
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_acheteur_commandes_history(): void
    {
        $acheteur = $this->actingAsAcheteur();
        [$e1, $s1] = $this->createEleveurWithStock();
        [$e2, $s2] = $this->createEleveurWithStock();

        Commande::factory()->create(['acheteur_id' => $acheteur->id, 'eleveur_id' => $e1->id, 'stock_id' => $s1->id]);
        Commande::factory()->create(['acheteur_id' => $acheteur->id, 'eleveur_id' => $e2->id, 'stock_id' => $s2->id]);

        $response = $this->getJson('/api/acheteur/commandes');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_returns_only_own_commandes_for_acheteur(): void
    {
        $acheteur = $this->actingAsAcheteur();
        $other    = User::factory()->acheteur()->create();
        [$e, $s]  = $this->createEleveurWithStock();

        Commande::factory()->create(['acheteur_id' => $acheteur->id, 'eleveur_id' => $e->id, 'stock_id' => $s->id]);
        Commande::factory()->create(['acheteur_id' => $other->id,    'eleveur_id' => $e->id, 'stock_id' => $s->id]);

        $response = $this->getJson('/api/acheteur/commandes');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_commandes_by_statut(): void
    {
        $acheteur = $this->actingAsAcheteur();
        [$e, $s]  = $this->createEleveurWithStock();

        Commande::factory()->create(['acheteur_id' => $acheteur->id, 'eleveur_id' => $e->id, 'stock_id' => $s->id, 'statut_commande' => 'confirmee']);
        Commande::factory()->create(['acheteur_id' => $acheteur->id, 'eleveur_id' => $e->id, 'stock_id' => $s->id, 'statut_commande' => 'livree']);

        $response = $this->getJson('/api/acheteur/commandes?statut=confirmee');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-10 — GET /api/eleveur/commandes
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_eleveur_commandes_history(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock();
        Sanctum::actingAs($eleveur);

        $acheteur = User::factory()->acheteur()->create();
        Commande::factory()->count(3)->create([
            'eleveur_id'  => $eleveur->id,
            'acheteur_id' => $acheteur->id,
            'stock_id'    => $stock->id,
        ]);

        $response = $this->getJson('/api/eleveur/commandes');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    #[Test]
    public function it_returns_only_own_commandes_for_eleveur(): void
    {
        [$eleveur1, $stock1] = $this->createEleveurWithStock();
        [$eleveur2, $stock2] = $this->createEleveurWithStock();
        Sanctum::actingAs($eleveur1);

        $acheteur = User::factory()->acheteur()->create();

        Commande::factory()->create(['eleveur_id' => $eleveur1->id, 'acheteur_id' => $acheteur->id, 'stock_id' => $stock1->id]);
        Commande::factory()->create(['eleveur_id' => $eleveur2->id, 'acheteur_id' => $acheteur->id, 'stock_id' => $stock2->id]);

        $response = $this->getJson('/api/eleveur/commandes');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-11 — GET /api/acheteur/commandes/{id}
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_commande_detail(): void
    {
        $acheteur = $this->actingAsAcheteur();
        [$e, $s]  = $this->createEleveurWithStock();

        $commande = Commande::factory()->create([
            'acheteur_id' => $acheteur->id,
            'eleveur_id'  => $e->id,
            'stock_id'    => $s->id,
        ]);

        $response = $this->getJson("/api/acheteur/commandes/{$commande->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $commande->id)
            ->assertJsonStructure(['data' => ['id', 'statut_commande', 'montant_total', 'stock', 'eleveur']]);
    }

    #[Test]
    public function it_returns_404_for_unknown_commande(): void
    {
        $this->actingAsAcheteur();

        $this->getJson('/api/acheteur/commandes/9999')->assertStatus(404);
    }

    #[Test]
    public function it_returns_403_when_accessing_another_acheteur_commande(): void
    {
        $acheteur = $this->actingAsAcheteur();
        $other    = User::factory()->acheteur()->create();
        [$e, $s]  = $this->createEleveurWithStock();

        $commande = Commande::factory()->create([
            'acheteur_id' => $other->id,
            'eleveur_id'  => $e->id,
            'stock_id'    => $s->id,
        ]);

        $this->getJson("/api/acheteur/commandes/{$commande->id}")->assertStatus(403);
    }
}