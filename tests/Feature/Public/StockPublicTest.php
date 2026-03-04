<?php

namespace Tests\Feature\Public;

use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature : Sprint 4 — Recherche & Découverte
 *
 *   RCH-01 : GET /api/stocks                   — Liste publique paginée
 *   RCH-02 : ?ville=                            — Filtre ville
 *   RCH-03 : ?prix_min= & ?prix_max=            — Filtre prix
 *   RCH-04 : ?poids_min=                        — Filtre poids
 *   RCH-05 : ?mode_vente=                       — Filtre mode vente
 *   RCH-06 : ?certifie=1                        — Filtre certifiés
 *   RCH-07 : ?sort=                             — Tri résultats
 *   RCH-08 : ?q=                                — Recherche full-text
 *   RCH-09 : GET /api/eleveurs/carte            — Carte GPS
 *   RCH-10 : GET /api/stocks/{id}               — Détail + vues (STK-09)
 *
 * Fichier : tests/Feature/Public/StockPublicTest.php
 * Lancer  : php artisan test --filter=StockPublicTest
 */
class StockPublicTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper : crée un éleveur avec profil et stock disponible ─

    private function createEleveurWithStock(array $eleveurData = [], array $profileData = [], array $stockData = []): array
    {
        $eleveur = User::factory()->eleveur()->create(array_merge([
            'is_verified' => true,
            'is_active'   => true,
            'ville'       => 'Dakar',
        ], $eleveurData));

        $eleveur->eleveurProfile()->create(array_merge([
            'nom_poulailler' => 'Ferme Test',
            'description'    => 'Description test',
            'localisation'   => 'Dakar',
            'latitude'       => 14.6928,
            'longitude'      => -17.4467,
            'is_certified'   => false,
            'note_moyenne'   => 0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ], $profileData));

        $stock = Stock::factory()->create(array_merge([
            'eleveur_id'          => $eleveur->id,
            'statut'              => 'disponible',
            'date_disponibilite'  => now()->toDateString(),
            'prix_par_kg'         => 2000,
            'poids_moyen_kg'      => 2.0,
            'mode_vente'          => 'vivant',
        ], $stockData));

        return [$eleveur, $stock];
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-01 — GET /api/stocks
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_public_stocks_list(): void
    {
        $this->createEleveurWithStock();
        $this->createEleveurWithStock();

        $response = $this->getJson('/api/stocks');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [['id', 'titre', 'prix_par_kg', 'mode_vente', 'statut', 'eleveur']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_is_accessible_without_authentication(): void
    {
        $this->createEleveurWithStock();

        $this->getJson('/api/stocks')->assertStatus(200);
    }

    #[Test]
    public function it_returns_only_disponible_stocks(): void
    {
        [$eleveur] = $this->createEleveurWithStock();

        Stock::factory()->create(['eleveur_id' => $eleveur->id, 'statut' => 'epuise']);
        Stock::factory()->create(['eleveur_id' => $eleveur->id, 'statut' => 'expire']);
        Stock::factory()->create(['eleveur_id' => $eleveur->id, 'statut' => 'reserve']);

        $response = $this->getJson('/api/stocks');

        // Seulement le 1 stock disponible créé dans createEleveurWithStock
        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_excludes_stocks_of_inactive_eleveurs(): void
    {
        $this->createEleveurWithStock(['is_active' => false]);
        $this->createEleveurWithStock(['is_active' => true]);

        $response = $this->getJson('/api/stocks');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_paginates_12_per_page(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createEleveurWithStock();
        }

        $response = $this->getJson('/api/stocks');

        $this->assertCount(12, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    #[Test]
    public function it_embeds_eleveur_info_in_stock(): void
    {
        $this->createEleveurWithStock();

        $response = $this->getJson('/api/stocks');

        $eleveur = $response->json('data.0.eleveur');
        $this->assertArrayHasKey('id', $eleveur);
        $this->assertArrayHasKey('name', $eleveur);
        $this->assertArrayHasKey('ville', $eleveur);
        $this->assertArrayHasKey('profil', $eleveur);
        $this->assertArrayNotHasKey('email', $eleveur);
        $this->assertArrayNotHasKey('phone', $eleveur);
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-02 — Filtre par ville
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_filters_by_ville(): void
    {
        $this->createEleveurWithStock(['ville' => 'Dakar']);
        $this->createEleveurWithStock(['ville' => 'Thiès']);
        $this->createEleveurWithStock(['ville' => 'Saint-Louis']);

        $response = $this->getJson('/api/stocks?ville=Dakar');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_ville_case_insensitive(): void
    {
        $this->createEleveurWithStock(['ville' => 'Dakar']);

        $response = $this->getJson('/api/stocks?ville=dakar');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_ville_partial_match(): void
    {
        $this->createEleveurWithStock(['ville' => 'Dakar Plateau']);
        $this->createEleveurWithStock(['ville' => 'Dakar Yoff']);
        $this->createEleveurWithStock(['ville' => 'Thiès']);

        $response = $this->getJson('/api/stocks?ville=Dakar');

        $this->assertEquals(2, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-03 — Filtre par prix
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_filters_by_prix_min(): void
    {
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 1500]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 2500]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 3500]);

        $response = $this->getJson('/api/stocks?prix_min=2000');

        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_by_prix_max(): void
    {
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 1500]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 2500]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 3500]);

        $response = $this->getJson('/api/stocks?prix_max=2500');

        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_by_prix_range(): void
    {
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 1000]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 2000]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 3000]);

        $response = $this->getJson('/api/stocks?prix_min=1500&prix_max=2500');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-04 — Filtre par poids minimum
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_filters_by_poids_min(): void
    {
        $this->createEleveurWithStock(stockData: ['poids_moyen_kg' => 1.5]);
        $this->createEleveurWithStock(stockData: ['poids_moyen_kg' => 2.5]);
        $this->createEleveurWithStock(stockData: ['poids_moyen_kg' => 3.5]);

        $response = $this->getJson('/api/stocks?poids_min=2.0');

        $this->assertEquals(2, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-05 — Filtre par mode de vente
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_filters_by_mode_vente(): void
    {
        $this->createEleveurWithStock(stockData: ['mode_vente' => 'vivant']);
        $this->createEleveurWithStock(stockData: ['mode_vente' => 'abattu']);
        $this->createEleveurWithStock(stockData: ['mode_vente' => 'les_deux']);

        $response = $this->getJson('/api/stocks?mode_vente=vivant');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-06 — Filtre éleveurs certifiés
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_filters_certified_eleveurs_only(): void
    {
        $this->createEleveurWithStock(profileData: ['is_certified' => true]);
        $this->createEleveurWithStock(profileData: ['is_certified' => true]);
        $this->createEleveurWithStock(profileData: ['is_certified' => false]);

        $response = $this->getJson('/api/stocks?certifie=1');

        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_returns_all_when_certifie_not_set(): void
    {
        $this->createEleveurWithStock(profileData: ['is_certified' => true]);
        $this->createEleveurWithStock(profileData: ['is_certified' => false]);

        $response = $this->getJson('/api/stocks');

        $this->assertEquals(2, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-07 — Tri résultats
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sorts_by_prix_asc(): void
    {
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 3000]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 1000]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 2000]);

        $response = $this->getJson('/api/stocks?sort=prix_asc');

        $prices = collect($response->json('data'))->pluck('prix_par_kg')->map(fn($p) => (int) $p)->values()->toArray();
        $this->assertEquals([1000, 2000, 3000], $prices);
    }

    #[Test]
    public function it_sorts_by_prix_desc(): void
    {
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 1000]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 3000]);
        $this->createEleveurWithStock(stockData: ['prix_par_kg' => 2000]);

        $response = $this->getJson('/api/stocks?sort=prix_desc');

        $prices = collect($response->json('data'))->pluck('prix_par_kg')->map(fn($p) => (int) $p)->values()->toArray();
        $this->assertEquals([3000, 2000, 1000], $prices);
    }

    #[Test]
    public function it_sorts_by_note_desc(): void
    {
        $this->createEleveurWithStock(profileData: ['note_moyenne' => 3.0]);
        $this->createEleveurWithStock(profileData: ['note_moyenne' => 5.0]);
        $this->createEleveurWithStock(profileData: ['note_moyenne' => 4.0]);

        $response = $this->getJson('/api/stocks?sort=note_desc');

        $notes = collect($response->json('data'))
            ->pluck('eleveur.profil.note_moyenne')
            ->map(fn($n) => (float) $n)
            ->values()
            ->toArray();

        $this->assertEquals([5.0, 4.0, 3.0], $notes);
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-08 — Recherche full-text
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_searches_by_titre(): void
    {
        [$e1, $s1] = $this->createEleveurWithStock();
        $s1->update(['titre' => 'Poulets fermiers bio Dakar']);

        [$e2, $s2] = $this->createEleveurWithStock();
        $s2->update(['titre' => 'Canards du terroir']);

        $response = $this->getJson('/api/stocks?q=Poulets');

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertStringContainsString('Poulets', $response->json('data.0.titre'));
    }

    #[Test]
    public function it_searches_by_description(): void
    {
        [$e, $s] = $this->createEleveurWithStock();
        $s->update(['description' => 'Elevés en plein air nourris au grain naturel sénégalais']);

        $this->createEleveurWithStock();

        $response = $this->getJson('/api/stocks?q=plein+air');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_search_is_case_insensitive(): void
    {
        [$e, $s] = $this->createEleveurWithStock();
        $s->update(['titre' => 'Poulets Fermiers Bio']);

        $response = $this->getJson('/api/stocks?q=poulets');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_can_combine_multiple_filters(): void
    {
        // Match : Dakar + vivant + prix 2000
        $this->createEleveurWithStock(
            eleveurData:  ['ville' => 'Dakar'],
            stockData:    ['mode_vente' => 'vivant', 'prix_par_kg' => 2000]
        );
        // No match : Thiès
        $this->createEleveurWithStock(
            eleveurData:  ['ville' => 'Thiès'],
            stockData:    ['mode_vente' => 'vivant', 'prix_par_kg' => 2000]
        );
        // No match : prix trop élevé
        $this->createEleveurWithStock(
            eleveurData:  ['ville' => 'Dakar'],
            stockData:    ['mode_vente' => 'vivant', 'prix_par_kg' => 5000]
        );

        $response = $this->getJson('/api/stocks?ville=Dakar&mode_vente=vivant&prix_max=3000');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-09 — Carte des éleveurs
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_eleveurs_with_coordinates(): void
    {
        $this->createEleveurWithStock(profileData: [
            'latitude'  => 14.6928,
            'longitude' => -17.4467,
        ]);
        $this->createEleveurWithStock(profileData: [
            'latitude'  => 14.7167,
            'longitude' => -17.4677,
        ]);

        $response = $this->getJson('/api/eleveurs/carte');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [['id', 'name', 'ville', 'nom_poulailler', 'latitude', 'longitude', 'is_certified', 'note_moyenne']],
                'meta' => ['total'],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_excludes_eleveurs_without_coordinates(): void
    {
        // Avec coordonnées
        $this->createEleveurWithStock(profileData: ['latitude' => 14.69, 'longitude' => -17.44]);

        // Sans coordonnées
        $eleveur2 = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $eleveur2->eleveurProfile()->create([
            'nom_poulailler' => 'Sans GPS',
            'description'    => 'test',
            'localisation'   => 'Dakar',
            'latitude'       => null,
            'longitude'      => null,
            'is_certified'   => false,
            'note_moyenne'   => 0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ]);

        $response = $this->getJson('/api/eleveurs/carte');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_carte_by_certifie(): void
    {
        $this->createEleveurWithStock(profileData: ['is_certified' => true, 'latitude' => 14.69, 'longitude' => -17.44]);
        $this->createEleveurWithStock(profileData: ['is_certified' => false, 'latitude' => 14.70, 'longitude' => -17.45]);

        $response = $this->getJson('/api/eleveurs/carte?certifie=1');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_does_not_expose_email_in_carte(): void
    {
        $this->createEleveurWithStock(profileData: ['latitude' => 14.69, 'longitude' => -17.44]);

        $response = $this->getJson('/api/eleveurs/carte');

        $this->assertArrayNotHasKey('email', $response->json('data.0'));
        $this->assertArrayNotHasKey('phone', $response->json('data.0'));
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-10 + STK-09 — Détail stock + compteur vues
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_stock_detail(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock();

        $response = $this->getJson("/api/stocks/{$stock->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'id', 'titre', 'description', 'prix_par_kg',
                    'photos', 'statut', 'vues', 'eleveur',
                ],
            ]);

        $this->assertEquals($stock->id, $response->json('data.id'));
    }

    #[Test]
    public function it_increments_vues_on_show(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock();
        $stock->update(['vues' => 10]);

        $this->getJson("/api/stocks/{$stock->id}")->assertStatus(200);

        $this->assertEquals(11, $stock->fresh()->vues);
    }

    #[Test]
    public function it_increments_vues_on_each_visit(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock();
        $stock->update(['vues' => 0]);

        $this->getJson("/api/stocks/{$stock->id}");
        $this->getJson("/api/stocks/{$stock->id}");
        $this->getJson("/api/stocks/{$stock->id}");

        $this->assertEquals(3, $stock->fresh()->vues);
    }

    #[Test]
    public function it_returns_404_for_unknown_stock(): void
    {
        $this->getJson('/api/stocks/9999')
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_404_for_non_disponible_stock(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock(stockData: ['statut' => 'epuise']);

        $this->getJson("/api/stocks/{$stock->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_for_stock_of_inactive_eleveur(): void
    {
        [$eleveur, $stock] = $this->createEleveurWithStock(['is_active' => false]);

        $this->getJson("/api/stocks/{$stock->id}")
            ->assertStatus(404);
    }
}