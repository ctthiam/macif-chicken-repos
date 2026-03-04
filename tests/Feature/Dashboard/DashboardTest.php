<?php

namespace Tests\Feature\Dashboard;

use App\Models\Abonnement;
use App\Models\Commande;
use App\Models\EleveurProfile;
use App\Models\Favori;
use App\Models\Litige;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   DSH-01/02/03/04/05 — Dashboard éleveur  (GET /api/eleveur/dashboard)
 *   DSH-06/07/08       — Dashboard acheteur (GET /api/acheteur/dashboard)
 *   DSH-09             — Dashboard admin    (GET /api/admin/dashboard)
 *   DSH-10             — Export CSV         (GET /api/admin/finances/export)
 *
 * Fichier : tests/Feature/Dashboard/DashboardTest.php
 * Lancer  : php artisan test --filter=DashboardTest
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function eleveur(): User
    {
        $u = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $u->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test',
            'description'    => 'Ferme de test',
            'localisation'   => 'Dakar',
            'is_certified'   => false,
            'note_moyenne'   => 4.2,
            'nombre_avis'    => 8,
            'photos'         => [],
        ]);
        return $u;
    }

    private function acheteur(): User
    {
        return User::factory()->acheteur()->create(['is_verified' => true, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    }

    private function commandeLivree(User $eleveur, User $acheteur, int $montant = 50000): Commande
    {
        $stock = Stock::factory()->create(['eleveur_id' => $eleveur->id]);
        return Commande::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'acheteur_id'           => $acheteur->id,
            'stock_id'              => $stock->id,
            'statut_commande'       => 'livree',
            'statut_paiement'       => 'libere',
            'montant_total'         => $montant,
            'montant_eleveur'       => (int)($montant * 0.93),
            'commission_plateforme' => (int)($montant * 0.07),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // DSH-01/02/03/04/05 — Dashboard éleveur
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_eleveur_dashboard_structure(): void
    {
        $eleveur = $this->eleveur();
        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'chiffre_affaires' => ['mensuel', 'annuel', 'mois', 'annee'],
                    'commandes_en_cours' => ['total', 'par_statut'],
                    'stocks'           => ['total_annonces', 'total_quantite'],
                    'ventes_30j',
                    'avis'             => ['note_moyenne', 'nombre_avis'],
                ],
            ]);
    }

    #[Test]
    public function it_calculates_ca_mensuel_correctly(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();

        // Deux commandes livrées ce mois
        $this->commandeLivree($eleveur, $acheteur, 50000);
        $this->commandeLivree($eleveur, $acheteur, 30000);

        Sanctum::actingAs($eleveur);
        $response = $this->getJson('/api/eleveur/dashboard');

        // montant_eleveur = 93% du montant_total
        $expectedMensuel = (int)(50000 * 0.93) + (int)(30000 * 0.93);
        $this->assertEquals($expectedMensuel, $response->json('data.chiffre_affaires.mensuel'));
    }

    #[Test]
    public function it_counts_commandes_en_cours_by_statut(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $stock    = Stock::factory()->create(['eleveur_id' => $eleveur->id]);

        Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'en_preparation',
        ]);
        Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'en_livraison',
        ]);

        Sanctum::actingAs($eleveur);
        $response = $this->getJson('/api/eleveur/dashboard');

        $this->assertEquals(2, $response->json('data.commandes_en_cours.total'));
        $this->assertEquals(1, $response->json('data.commandes_en_cours.par_statut.en_preparation'));
        $this->assertEquals(1, $response->json('data.commandes_en_cours.par_statut.en_livraison'));
    }

    #[Test]
    public function it_counts_stocks_actifs(): void
    {
        $eleveur = $this->eleveur();

        Stock::factory()->count(2)->create(['eleveur_id' => $eleveur->id, 'statut' => 'disponible', 'quantite_disponible' => 20]);
        Stock::factory()->create(['eleveur_id' => $eleveur->id, 'statut' => 'epuise', 'quantite_disponible' => 0]);

        Sanctum::actingAs($eleveur);
        $response = $this->getJson('/api/eleveur/dashboard');

        $this->assertEquals(2,  $response->json('data.stocks.total_annonces'));
        $this->assertEquals(40, $response->json('data.stocks.total_quantite'));
    }

    #[Test]
    public function it_returns_ventes_30j_as_array(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $this->commandeLivree($eleveur, $acheteur, 25000);

        Sanctum::actingAs($eleveur);
        $response = $this->getJson('/api/eleveur/dashboard');

        $this->assertIsArray($response->json('data.ventes_30j'));
    }

    #[Test]
    public function it_returns_note_moyenne_from_profile(): void
    {
        $eleveur = $this->eleveur();
        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/dashboard');

        $this->assertEquals(4.2, $response->json('data.avis.note_moyenne'));
        $this->assertEquals(8,   $response->json('data.avis.nombre_avis'));
    }

    #[Test]
    public function it_requires_auth_for_eleveur_dashboard(): void
    {
        $this->getJson('/api/eleveur/dashboard')->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    // DSH-06/07/08 — Dashboard acheteur
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_acheteur_dashboard_structure(): void
    {
        $acheteur = $this->acheteur();
        Sanctum::actingAs($acheteur);

        $response = $this->getJson('/api/acheteur/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'commandes' => ['total', 'par_statut', 'dernieres'],
                    'depenses'  => ['mois_en_cours', 'annee', 'mois', 'annee_label'],
                    'favoris'   => ['total', 'eleveurs'],
                ],
            ]);
    }

    #[Test]
    public function it_counts_acheteur_commandes_by_statut(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $stock    = Stock::factory()->create(['eleveur_id' => $eleveur->id]);

        Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'livree',
        ]);
        Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'confirmee',
        ]);

        Sanctum::actingAs($acheteur);
        $response = $this->getJson('/api/acheteur/dashboard');

        $this->assertEquals(2, $response->json('data.commandes.total'));
        $this->assertEquals(1, $response->json('data.commandes.par_statut.livree'));
        $this->assertEquals(1, $response->json('data.commandes.par_statut.confirmee'));
    }

    #[Test]
    public function it_calculates_depenses_du_mois(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $stock    = Stock::factory()->create(['eleveur_id' => $eleveur->id]);

        Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'livree',
            'montant_total'   => 45000,
        ]);

        Sanctum::actingAs($acheteur);
        $response = $this->getJson('/api/acheteur/dashboard');

        $this->assertEquals(45000, $response->json('data.depenses.mois_en_cours'));
    }

    #[Test]
    public function it_returns_eleveurs_favoris(): void
    {
        $acheteur = $this->acheteur();
        $eleveur  = $this->eleveur();

        Favori::create(['user_id' => $acheteur->id, 'eleveur_id' => $eleveur->id]);

        Sanctum::actingAs($acheteur);
        $response = $this->getJson('/api/acheteur/dashboard');

        $this->assertEquals(1,          $response->json('data.favoris.total'));
        $this->assertEquals($eleveur->id, $response->json('data.favoris.eleveurs.0.eleveur_id'));
    }

    #[Test]
    public function it_requires_auth_for_acheteur_dashboard(): void
    {
        $this->getJson('/api/acheteur/dashboard')->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    // DSH-09 — Dashboard admin KPIs
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_admin_dashboard_kpis(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'users'    => ['total', 'eleveurs', 'acheteurs', 'nouveaux_jour'],
                    'commandes'=> ['aujourd_hui', 'ce_mois', 'par_statut'],
                    'revenus'  => ['commission_mois', 'commission_total', 'volume_mois'],
                    'litiges'  => ['ouverts', 'total'],
                    'genere_le',
                ],
            ]);
    }

    #[Test]
    public function it_counts_litiges_ouverts_in_admin_dashboard(): void
    {
        $admin    = $this->admin();
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $stock    = Stock::factory()->create(['eleveur_id' => $eleveur->id]);
        $commande = Commande::factory()->create([
            'eleveur_id'  => $eleveur->id,
            'acheteur_id' => $acheteur->id,
            'stock_id'    => $stock->id,
        ]);

        Litige::create([
            'commande_id'  => $commande->id,
            'demandeur_id' => $acheteur->id,
            'raison'       => 'Produit non conforme à la commande.',
            'statut'       => 'ouvert',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/dashboard');

        $this->assertEquals(1, $response->json('data.litiges.ouverts'));
    }

    #[Test]
    public function it_requires_admin_role_for_dashboard(): void
    {
        $acheteur = $this->acheteur();
        Sanctum::actingAs($acheteur);

        $this->getJson('/api/admin/dashboard')->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // DSH-10 — Export CSV
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_exports_csv_with_correct_content_type(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/finances/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function it_exports_csv_with_correct_headers(): void
    {
        $admin    = $this->admin();
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $this->commandeLivree($eleveur, $acheteur, 60000);

        Sanctum::actingAs($admin);
        $response = $this->get('/api/admin/finances/export');

        $csv = $response->getContent();
        $this->assertStringContainsString('ID Commande', $csv);
        $this->assertStringContainsString('Commission plateforme', $csv);
    }

    #[Test]
    public function it_filters_csv_by_annee(): void
    {
        $admin    = $this->admin();
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $this->commandeLivree($eleveur, $acheteur, 40000);

        Sanctum::actingAs($admin);
        $response = $this->get('/api/admin/finances/export?annee=' . now()->year);

        $response->assertStatus(200);
        $csv = $response->getContent();
        // Au moins la ligne d'en-tête + une ligne de données
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    #[Test]
    public function it_requires_admin_role_for_csv_export(): void
    {
        $acheteur = $this->acheteur();
        Sanctum::actingAs($acheteur);

        $this->get('/api/admin/finances/export')->assertStatus(403);
    }
}