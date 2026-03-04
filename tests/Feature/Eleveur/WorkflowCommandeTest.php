<?php

namespace Tests\Feature\Eleveur;

use App\Models\Commande;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   CMD-03 — Confirmation par éleveur     (PUT /api/eleveur/commandes/{id} action:confirmer)
 *   CMD-04 — Annulation par acheteur      (DELETE /api/acheteur/commandes/{id})
 *   CMD-05 — Workflow livraison éleveur   (PUT /api/eleveur/commandes/{id} action:en_livraison|livree)
 *
 * Fichier : tests/Feature/Eleveur/WorkflowCommandeTest.php
 * Lancer  : php artisan test --filter=WorkflowCommandeTest
 */
class WorkflowCommandeTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function createCommande(string $statut = 'confirmee'): array
    {
        $eleveur  = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true, 'is_active' => true]);

        $stock = Stock::factory()->create([
            'eleveur_id'          => $eleveur->id,
            'statut'              => 'reserve',
            'quantite_disponible' => 45,
        ]);

        $commande = Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => $statut,
            'statut_paiement' => 'en_attente',
            'quantite'        => 5,
        ]);

        return [$eleveur, $acheteur, $stock, $commande];
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-03 — action: confirmer
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function eleveur_can_confirm_commande(): void
    {
        [$eleveur, $acheteur, $stock, $commande] = $this->createCommande('confirmee');
        Sanctum::actingAs($eleveur);

        $response = $this->putJson("/api/eleveur/commandes/{$commande->id}", [
            'action' => 'confirmer',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.statut_commande', 'en_preparation');

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_commande' => 'en_preparation',
        ]);
    }

    #[Test]
    public function it_rejects_confirmer_when_not_confirmee(): void
    {
        [$eleveur, , , $commande] = $this->createCommande('en_preparation');
        Sanctum::actingAs($eleveur);

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'confirmer'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-05 — action: en_livraison + livree
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function eleveur_can_set_en_livraison(): void
    {
        [$eleveur, , , $commande] = $this->createCommande('en_preparation');
        Sanctum::actingAs($eleveur);

        $response = $this->putJson("/api/eleveur/commandes/{$commande->id}", [
            'action' => 'en_livraison',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.statut_commande', 'en_livraison');
    }

    #[Test]
    public function eleveur_can_set_livree(): void
    {
        [$eleveur, , , $commande] = $this->createCommande('en_livraison');
        Sanctum::actingAs($eleveur);

        $response = $this->putJson("/api/eleveur/commandes/{$commande->id}", [
            'action' => 'livree',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.statut_commande', 'livree');
    }

    #[Test]
    public function it_rejects_en_livraison_when_not_en_preparation(): void
    {
        [$eleveur, , , $commande] = $this->createCommande('confirmee');
        Sanctum::actingAs($eleveur);

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'en_livraison'])
            ->assertStatus(422);
    }

    #[Test]
    public function it_rejects_livree_when_not_en_livraison(): void
    {
        [$eleveur, , , $commande] = $this->createCommande('en_preparation');
        Sanctum::actingAs($eleveur);

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'livree'])
            ->assertStatus(422);
    }

    #[Test]
    public function full_workflow_confirmee_to_livree(): void
    {
        [$eleveur, , , $commande] = $this->createCommande('confirmee');
        Sanctum::actingAs($eleveur);

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'confirmer'])
            ->assertJsonPath('data.statut_commande', 'en_preparation');

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'en_livraison'])
            ->assertJsonPath('data.statut_commande', 'en_livraison');

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'livree'])
            ->assertJsonPath('data.statut_commande', 'livree');
    }

    #[Test]
    public function it_returns_404_for_unknown_commande(): void
    {
        $eleveur = User::factory()->eleveur()->create(['is_verified' => true]);
        Sanctum::actingAs($eleveur);

        $this->putJson('/api/eleveur/commandes/9999', ['action' => 'confirmer'])
            ->assertStatus(404);
    }

    #[Test]
    public function it_returns_403_when_updating_another_eleveur_commande(): void
    {
        [$eleveur, , , $commande] = $this->createCommande('confirmee');
        $other = User::factory()->eleveur()->create(['is_verified' => true]);
        Sanctum::actingAs($other);

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'confirmer'])
            ->assertStatus(403);
    }

    #[Test]
    public function it_rejects_invalid_action(): void
    {
        [$eleveur, , , $commande] = $this->createCommande('confirmee');
        Sanctum::actingAs($eleveur);

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'supprimer'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    #[Test]
    public function it_requires_authentication_for_update(): void
    {
        [, , , $commande] = $this->createCommande();

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'confirmer'])
            ->assertStatus(401);
    }

    #[Test]
    public function it_returns_403_for_acheteur_on_eleveur_route(): void
    {
        [, $acheteur, , $commande] = $this->createCommande();
        Sanctum::actingAs($acheteur);

        $this->putJson("/api/eleveur/commandes/{$commande->id}", ['action' => 'confirmer'])
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-04 — DELETE /api/acheteur/commandes/{id}
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function acheteur_can_cancel_confirmee_commande(): void
    {
        [$eleveur, $acheteur, $stock, $commande] = $this->createCommande('confirmee');
        Sanctum::actingAs($acheteur);

        $response = $this->deleteJson("/api/acheteur/commandes/{$commande->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.statut_commande', 'annulee')
            ->assertJsonPath('data.statut_paiement', 'rembourse');
    }

    #[Test]
    public function it_restores_stock_quantite_on_cancel(): void
    {
        [$eleveur, $acheteur, $stock, $commande] = $this->createCommande('confirmee');
        Sanctum::actingAs($acheteur);

        $quantiteAvant = $stock->quantite_disponible; // 45

        $this->deleteJson("/api/acheteur/commandes/{$commande->id}");

        $this->assertDatabaseHas('stocks', [
            'id'                  => $stock->id,
            'quantite_disponible' => $quantiteAvant + $commande->quantite, // 45 + 5 = 50
            'statut'              => 'disponible',
        ]);
    }

    #[Test]
    public function it_cannot_cancel_en_preparation_commande(): void
    {
        [$eleveur, $acheteur, , $commande] = $this->createCommande('en_preparation');
        Sanctum::actingAs($acheteur);

        $this->deleteJson("/api/acheteur/commandes/{$commande->id}")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_cannot_cancel_livree_commande(): void
    {
        [$eleveur, $acheteur, , $commande] = $this->createCommande('livree');
        Sanctum::actingAs($acheteur);

        $this->deleteJson("/api/acheteur/commandes/{$commande->id}")
            ->assertStatus(422);
    }

    #[Test]
    public function it_returns_403_when_cancelling_another_acheteur_commande(): void
    {
        [$eleveur, $acheteur, , $commande] = $this->createCommande('confirmee');
        $other = User::factory()->acheteur()->create(['is_verified' => true]);
        Sanctum::actingAs($other);

        $this->deleteJson("/api/acheteur/commandes/{$commande->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function it_returns_404_when_cancelling_unknown_commande(): void
    {
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true]);
        Sanctum::actingAs($acheteur);

        $this->deleteJson('/api/acheteur/commandes/9999')
            ->assertStatus(404);
    }

    #[Test]
    public function it_requires_authentication_for_cancel(): void
    {
        [, , , $commande] = $this->createCommande('confirmee');

        $this->deleteJson("/api/acheteur/commandes/{$commande->id}")
            ->assertStatus(401);
    }
}