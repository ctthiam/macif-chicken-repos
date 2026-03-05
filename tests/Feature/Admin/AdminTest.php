<?php

namespace Tests\Feature\Admin;

use App\Models\Commande;
use App\Models\Litige;
use App\Models\Setting;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   ADM-02 — Liste utilisateurs avec filtres
 *   ADM-03 — Suspendre / réactiver un compte
 *   ADM-04 — Certifier un éleveur
 *   ADM-05 — Modération annonces
 *   ADM-06 — Vue commandes avec filtres
 *   ADM-07 — Résolution litiges
 *   ADM-08 — Configuration settings
 *
 * Fichier : tests/Feature/Admin/AdminTest.php
 * Lancer  : php artisan test --filter=AdminTest
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    }

    private function eleveur(): User
    {
        $u = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $u->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test',
            'description'    => 'Ferme de test.',
            'localisation'   => 'Dakar',
            'is_certified'   => false,
            'note_moyenne'   => 0.0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ]);
        return $u;
    }

    private function acheteur(): User
    {
        return User::factory()->acheteur()->create(['is_verified' => true, 'is_active' => true]);
    }

    private function commande(User $eleveur, User $acheteur, string $statut = 'confirmee'): Commande
    {
        $stock = Stock::factory()->create(['eleveur_id' => $eleveur->id]);
        return Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => $statut,
            'statut_paiement' => 'paye',
            'montant_total'   => 50000,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-02 — Liste utilisateurs
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_lists_all_users_for_admin(): void
    {
        $admin = $this->admin();
        $this->eleveur();
        $this->acheteur();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role', 'is_active']],
                'meta' => ['total'],
            ]);
    }

    #[Test]
    public function it_filters_users_by_role(): void
    {
        $admin = $this->admin();
        $this->eleveur();
        $this->acheteur();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?role=eleveur');

        $data = $response->json('data');
        foreach ($data as $user) {
            $this->assertEquals('eleveur', $user['role']);
        }
    }

    #[Test]
    public function it_filters_users_by_is_active(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();
        $eleveur->update(['is_active' => false]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?is_active=0');

        $data = $response->json('data');
        $this->assertTrue(collect($data)->contains('id', $eleveur->id));
    }

    #[Test]
    public function it_searches_users_by_name(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['name' => 'Mamadou Diallo', 'role' => 'acheteur', 'is_verified' => true]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?search=Mamadou');

        $data = $response->json('data');
        $this->assertTrue(collect($data)->contains('id', $user->id));
    }

    #[Test]
    public function it_requires_admin_role_to_list_users(): void
    {
        $acheteur = $this->acheteur();
        Sanctum::actingAs($acheteur);

        $this->getJson('/api/admin/users')->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-03 — Suspendre / réactiver
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_suspends_a_user(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/users/{$eleveur->id}/toggle-status");

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('users', ['id' => $eleveur->id, 'is_active' => false]);
    }

    #[Test]
    public function it_reactivates_a_suspended_user(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();
        $eleveur->update(['is_active' => false]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$eleveur->id}/toggle-status");

        $this->assertDatabaseHas('users', ['id' => $eleveur->id, 'is_active' => true]);
    }

    #[Test]
    public function it_cannot_toggle_admin_status(): void
    {
        $admin  = $this->admin();
        $admin2 = $this->admin();

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$admin2->id}/toggle-status")->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-04 — Certifier éleveur
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_certifies_an_eleveur(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/users/{$eleveur->id}/certifier");

        $response->assertStatus(200)->assertJsonPath('data.is_certified', true);
        $this->assertDatabaseHas('eleveur_profiles', [
            'user_id'      => $eleveur->id,
            'is_certified' => true,
        ]);
    }

    #[Test]
    public function it_toggles_certification(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();
        $eleveur->eleveurProfile->update(['is_certified' => true]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$eleveur->id}/certifier");

        $this->assertDatabaseHas('eleveur_profiles', [
            'user_id'      => $eleveur->id,
            'is_certified' => false,
        ]);
    }

    #[Test]
    public function it_cannot_certify_non_eleveur(): void
    {
        $admin    = $this->admin();
        $acheteur = $this->acheteur();

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$acheteur->id}/certifier")->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-05 — Modération annonces
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_masks_a_stock(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();
        $stock   = Stock::factory()->create(['eleveur_id' => $eleveur->id, 'statut' => 'disponible']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/stocks/{$stock->id}/moderer", [
            'action' => 'masquer',
            'raison' => 'Annonce non conforme aux règles de la plateforme.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'statut' => 'masque']);
    }

    #[Test]
    public function it_deletes_a_stock(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();
        $stock   = Stock::factory()->create(['eleveur_id' => $eleveur->id]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/stocks/{$stock->id}/moderer", [
            'action' => 'supprimer',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    #[Test]
    public function it_restores_a_masked_stock(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();
        $stock   = Stock::factory()->create(['eleveur_id' => $eleveur->id, 'statut' => 'masque']);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/stocks/{$stock->id}/moderer", [
            'action' => 'restaurer',
        ])->assertStatus(200);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'statut' => 'disponible']);
    }

    #[Test]
    public function it_validates_moderer_action(): void
    {
        $admin   = $this->admin();
        $eleveur = $this->eleveur();
        $stock   = Stock::factory()->create(['eleveur_id' => $eleveur->id]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/stocks/{$stock->id}/moderer", [
            'action' => 'invalide',
        ])->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-06 — Vue commandes
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_lists_all_commandes_for_admin(): void
    {
        $admin    = $this->admin();
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $this->commande($eleveur, $acheteur);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/commandes');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data', 'meta' => ['total']]);

        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_commandes_by_statut(): void
    {
        $admin    = $this->admin();
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $this->commande($eleveur, $acheteur, 'livree');
        $this->commande($eleveur, $acheteur, 'confirmee');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/commandes?statut_commande=livree');

        foreach ($response->json('data') as $c) {
            $this->assertEquals('livree', $c['statut_commande']);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-07 — Résolution litiges
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_resolves_litige_with_remboursement(): void
    {
        $admin    = $this->admin();
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        $litige = Litige::create([
            'commande_id'  => $commande->id,
            'demandeur_id' => $acheteur->id,
            'raison'       => 'Produit non conforme à la description de la commande.',
            'statut'       => 'ouvert',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/litiges/{$litige->id}/resoudre", [
            'decision'   => 'remboursement',
            'resolution' => 'Après vérification, remboursement accordé à l\'acheteur.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('litiges', [
            'id'     => $litige->id,
            'statut' => 'resolu_remboursement',
        ]);
    }

    #[Test]
    public function it_resolves_litige_with_liberation(): void
    {
        $admin    = $this->admin();
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        $litige = Litige::create([
            'commande_id'  => $commande->id,
            'demandeur_id' => $acheteur->id,
            'raison'       => 'Acheteur conteste sans raison valable la commande.',
            'statut'       => 'ouvert',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/litiges/{$litige->id}/resoudre", [
            'decision'   => 'liberation',
            'resolution' => 'Litige infondé, fonds libérés à l\'éleveur après vérification.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('litiges', [
            'id'     => $litige->id,
            'statut' => 'resolu_liberation',
        ]);
    }

    #[Test]
    public function it_cannot_resolve_already_resolved_litige(): void
    {
        $admin    = $this->admin();
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        $litige = Litige::create([
            'commande_id'  => $commande->id,
            'demandeur_id' => $acheteur->id,
            'raison'       => 'Litige déjà résolu auparavant par l\'équipe.',
            'statut'       => 'resolu_remboursement',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/litiges/{$litige->id}/resoudre", [
            'decision'   => 'liberation',
            'resolution' => 'Tentative de re-résolution d\'un litige déjà clôturé.',
        ])->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-08 — Settings
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_reads_settings(): void
    {
        $admin = $this->admin();
        Setting::set('taux_commission', '0.07');
        Setting::set('starter_prix', '5000');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/settings');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['taux_commission', 'starter_prix']]);
    }

    #[Test]
    public function it_updates_settings(): void
    {
        $admin = $this->admin();
        Setting::set('taux_commission', '0.07');

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/settings', [
            'taux_commission' => '0.09',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals('0.09', Setting::get('taux_commission'));
    }

    #[Test]
    public function it_validates_taux_commission_range(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings', [
            'taux_commission' => '1.5', // > 1 invalide
        ])->assertStatus(422);
    }

    #[Test]
    public function it_requires_admin_role_for_settings(): void
    {
        $acheteur = $this->acheteur();
        Sanctum::actingAs($acheteur);

        $this->getJson('/api/admin/settings')->assertStatus(403);
    }
}