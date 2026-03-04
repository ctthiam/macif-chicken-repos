<?php

namespace Tests\Feature\Acheteur;

use App\Jobs\LibererEscrowJob;
use App\Models\Commande;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   CMD-06 — Confirmation réception livraison  (POST /api/commandes/{id}/confirmer-livraison)
 *   CMD-07 — Libération automatique escrow 48h (Job LibererEscrowJob)
 *
 * Fichier : tests/Feature/Acheteur/ConfirmerLivraisonTest.php
 * Lancer  : php artisan test --filter=ConfirmerLivraisonTest
 *
 * ROUTE CORRECTE : /api/commandes/{id}/confirmer-livraison
 * (pas /api/acheteur/commandes — route partagée dans CommandeSharedController)
 */
class ConfirmerLivraisonTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper ──────────────────────────────────────────────────

    private function createCommandeLivree(array $overrides = []): array
    {
        $eleveur  = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true, 'is_active' => true]);

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'epuise',
        ]);

        $commande = Commande::factory()->create(array_merge([
            'eleveur_id'       => $eleveur->id,
            'acheteur_id'      => $acheteur->id,
            'stock_id'         => $stock->id,
            'statut_commande'  => 'livree',
            'statut_paiement'  => 'paye',
            'montant_total'    => 50000,
            'montant_eleveur'  => 46500,
            'escrow_libere_at' => null,
        ], $overrides));

        return [$eleveur, $acheteur, $stock, $commande];
    }

    /**
     * Force updated_at via DB::table pour contourner le timestamps auto de Laravel.
     * Nécessaire pour tester la logique 48h du LibererEscrowJob.
     */
    private function setUpdatedAt(Commande $commande, \Carbon\Carbon $date): void
    {
        DB::table('commandes')
            ->where('id', $commande->id)
            ->update(['updated_at' => $date->toDateTimeString()]);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-06 — POST /api/commandes/{id}/confirmer-livraison
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function acheteur_can_confirm_reception(): void
    {
        [$eleveur, $acheteur, $stock, $commande] = $this->createCommandeLivree();
        Sanctum::actingAs($acheteur);

        $response = $this->postJson("/api/commandes/{$commande->id}/confirmer-livraison");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.statut_paiement', 'libere');

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'libere',
        ]);

        $this->assertNotNull($commande->fresh()->escrow_libere_at);
    }

    #[Test]
    public function it_returns_success_message_on_confirmation(): void
    {
        [$e, $acheteur, , $commande] = $this->createCommandeLivree();
        Sanctum::actingAs($acheteur);

        $response = $this->postJson("/api/commandes/{$commande->id}/confirmer-livraison");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Réception confirmée. Les fonds ont été libérés à l\'éleveur.');
    }

    #[Test]
    public function it_returns_404_for_unknown_commande(): void
    {
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true]);
        Sanctum::actingAs($acheteur);

        $this->postJson('/api/commandes/9999/confirmer-livraison')
            ->assertStatus(404);
    }

    #[Test]
    public function it_returns_403_for_other_acheteur(): void
    {
        [$e, $acheteur, , $commande] = $this->createCommandeLivree();
        $other = User::factory()->acheteur()->create(['is_verified' => true]);
        Sanctum::actingAs($other);

        $this->postJson("/api/commandes/{$commande->id}/confirmer-livraison")
            ->assertStatus(403);
    }

    #[Test]
    public function it_rejects_when_commande_not_livree(): void
    {
        [$e, $acheteur, , $commande] = $this->createCommandeLivree([
            'statut_commande' => 'en_livraison',
        ]);
        Sanctum::actingAs($acheteur);

        $this->postJson("/api/commandes/{$commande->id}/confirmer-livraison")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_when_escrow_already_libere(): void
    {
        [$e, $acheteur, , $commande] = $this->createCommandeLivree([
            'statut_paiement'  => 'libere',
            'escrow_libere_at' => now(),
        ]);
        Sanctum::actingAs($acheteur);

        $this->postJson("/api/commandes/{$commande->id}/confirmer-livraison")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_when_paiement_not_paye(): void
    {
        [$e, $acheteur, , $commande] = $this->createCommandeLivree([
            'statut_paiement' => 'en_attente',
        ]);
        Sanctum::actingAs($acheteur);

        $this->postJson("/api/commandes/{$commande->id}/confirmer-livraison")
            ->assertStatus(422);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        [, , , $commande] = $this->createCommandeLivree();

        $this->postJson("/api/commandes/{$commande->id}/confirmer-livraison")
            ->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-07 — Job LibererEscrowJob (48h auto)
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function job_libere_commande_after_48h(): void
    {
        [$e, $a, , $commande] = $this->createCommandeLivree();

        // Forcer updated_at via DB::table (contourne timestamps auto Laravel)
        $this->setUpdatedAt($commande, now()->subHours(49));

        dispatch(new LibererEscrowJob());

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'libere',
        ]);
        $this->assertNotNull($commande->fresh()->escrow_libere_at);
    }

    #[Test]
    public function job_does_not_libere_before_48h(): void
    {
        [$e, $a, , $commande] = $this->createCommandeLivree();

        $this->setUpdatedAt($commande, now()->subHours(24));

        dispatch(new LibererEscrowJob());

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'paye',
        ]);
    }

    #[Test]
    public function job_does_not_libere_already_libere_commande(): void
    {
        [$e, $a, , $commande] = $this->createCommandeLivree([
            'statut_paiement'  => 'libere',
            'escrow_libere_at' => now()->subDay(),
        ]);

        $this->setUpdatedAt($commande, now()->subHours(49));

        dispatch(new LibererEscrowJob());

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'libere',
        ]);
    }

    #[Test]
    public function job_does_not_libere_when_litige_actif(): void
    {
        [$eleveur, $acheteur, , $commande] = $this->createCommandeLivree();
        $this->setUpdatedAt($commande, now()->subHours(49));

        \App\Models\Litige::create([
            'commande_id'  => $commande->id,
            'demandeur_id' => $acheteur->id,
            'raison'       => 'Produit non conforme',
            'statut'       => 'ouvert',
        ]);

        dispatch(new LibererEscrowJob());

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'paye',
        ]);
    }

    #[Test]
    public function job_skips_non_livree_commandes(): void
    {
        [$e, $a, , $commande] = $this->createCommandeLivree([
            'statut_commande' => 'en_livraison',
        ]);
        $this->setUpdatedAt($commande, now()->subHours(49));

        dispatch(new LibererEscrowJob());

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'paye',
        ]);
    }

    #[Test]
    public function job_handles_multiple_commandes(): void
    {
        [$e1, $a1, , $cmd1] = $this->createCommandeLivree();
        [$e2, $a2, , $cmd2] = $this->createCommandeLivree();
        $this->setUpdatedAt($cmd1, now()->subHours(50));
        $this->setUpdatedAt($cmd2, now()->subHours(72));

        [$e3, $a3, , $cmd3] = $this->createCommandeLivree();
        $this->setUpdatedAt($cmd3, now()->subHours(10));

        dispatch(new LibererEscrowJob());

        $this->assertDatabaseHas('commandes', ['id' => $cmd1->id, 'statut_paiement' => 'libere']);
        $this->assertDatabaseHas('commandes', ['id' => $cmd2->id, 'statut_paiement' => 'libere']);
        $this->assertDatabaseHas('commandes', ['id' => $cmd3->id, 'statut_paiement' => 'paye']);
    }

    #[Test]
    public function job_runs_without_error_when_no_eligible_commandes(): void
    {
        dispatch(new LibererEscrowJob());
        $this->assertTrue(true);
    }
}