<?php

namespace Tests\Feature\Paiement;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   PAY-06 — Reçu PDF téléchargeable  (GET /api/eleveur/transactions/{id}/recu)
 *   PAY-07 — Historique transactions  (GET /api/eleveur/transactions)
 *
 * Fichier : tests/Feature/Paiement/RecuTransactionTest.php
 * Lancer  : php artisan test --filter=RecuTransactionTest
 */
class RecuTransactionTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function createTransactionData(): array
    {
        $eleveur  = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true, 'is_active' => true]);

        $eleveur->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test',
            'description'    => 'Ferme de test',
            'localisation'   => 'Dakar',
            'is_certified'   => false,
            'note_moyenne'   => 4.0,
            'nombre_avis'    => 5,
            'photos'         => [],
        ]);

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'epuise',
        ]);

        $commande = Commande::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'acheteur_id'           => $acheteur->id,
            'stock_id'              => $stock->id,
            'statut_commande'       => 'livree',
            'statut_paiement'       => 'libere',
            'montant_total'         => 50000,
            'montant_eleveur'       => 46500,
            'commission_plateforme' => 3500,
            'quantite'              => 10,
            'mode_paiement'         => 'wave',
            'adresse_livraison'     => 'Dakar, Plateau',
            'escrow_libere_at'      => now(),
        ]);

        $paiement = Paiement::create([
            'commande_id'           => $commande->id,
            'user_id'               => $acheteur->id,
            'montant'               => 50000,
            'methode'               => 'wave',
            'reference_transaction' => 'MACIF-' . $commande->id . '-PDF001',
            'statut'                => 'confirme',
        ]);

        return [$eleveur, $acheteur, $stock, $commande, $paiement];
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-07 — GET /api/eleveur/transactions
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_eleveur_transactions(): void
    {
        [$eleveur, $acheteur, $stock, $commande, $paiement] = $this->createTransactionData();
        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/transactions');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [['id', 'reference_transaction', 'montant', 'methode', 'statut', 'commande']],
                'meta' => ['current_page', 'total', 'total_libere', 'total_en_cours'],
            ]);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_returns_only_own_transactions(): void
    {
        [$eleveur1] = $this->createTransactionData();
        $this->createTransactionData();
        Sanctum::actingAs($eleveur1);

        $response = $this->getJson('/api/eleveur/transactions');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_transactions_by_statut(): void
    {
        [$eleveur, $acheteur, $stock, $commande, $paiement] = $this->createTransactionData();

        $commande2 = Commande::factory()->create([
            'eleveur_id'  => $eleveur->id,
            'acheteur_id' => $acheteur->id,
            'stock_id'    => $stock->id,
        ]);
        Paiement::create([
            'commande_id'           => $commande2->id,
            'user_id'               => $acheteur->id,
            'montant'               => 20000,
            'methode'               => 'orange_money',
            'reference_transaction' => 'MACIF-' . $commande2->id . '-REMB',
            'statut'                => 'rembourse',
        ]);

        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/transactions?statut=confirme');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_returns_meta_totaux(): void
    {
        [$eleveur] = $this->createTransactionData();
        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/transactions');

        $this->assertArrayHasKey('total_libere', $response->json('meta'));
        $this->assertArrayHasKey('total_en_cours', $response->json('meta'));
    }

    #[Test]
    public function it_paginates_transactions_12_per_page(): void
    {
        $eleveur  = User::factory()->eleveur()->create(['is_verified' => true]);
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true]);

        for ($i = 0; $i < 15; $i++) {
            $stock = Stock::factory()->create(['eleveur_id' => $eleveur->id]);
            $cmd   = Commande::factory()->create([
                'eleveur_id'  => $eleveur->id,
                'acheteur_id' => $acheteur->id,
                'stock_id'    => $stock->id,
            ]);
            Paiement::create([
                'commande_id'           => $cmd->id,
                'user_id'               => $acheteur->id,
                'montant'               => 10000,
                'methode'               => 'wave',
                'reference_transaction' => 'REF-' . $i . '-' . uniqid(),
                'statut'                => 'confirme',
            ]);
        }

        Sanctum::actingAs($eleveur);
        $response = $this->getJson('/api/eleveur/transactions');

        $this->assertCount(12, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
    }

    #[Test]
    public function it_requires_authentication_for_transactions(): void
    {
        // getJson retourne 401 (Accept: application/json) — pas de redirect login
        $this->getJson('/api/eleveur/transactions')->assertStatus(401);
    }

    #[Test]
    public function it_returns_403_for_acheteur_on_transactions(): void
    {
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true]);
        Sanctum::actingAs($acheteur);

        $this->getJson('/api/eleveur/transactions')->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-06 — GET /api/eleveur/transactions/{id}/recu
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_generates_pdf_recu_for_eleveur(): void
    {
        [$eleveur, , , $commande] = $this->createTransactionData();
        Sanctum::actingAs($eleveur);

        $response = $this->get("/api/eleveur/transactions/{$commande->id}/recu");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function it_returns_pdf_with_correct_filename(): void
    {
        [$eleveur, , , $commande] = $this->createTransactionData();
        Sanctum::actingAs($eleveur);

        $response = $this->get("/api/eleveur/transactions/{$commande->id}/recu");

        $response->assertStatus(200);
        $this->assertStringContainsString(
            "recu-commande-{$commande->id}.pdf",
            $response->headers->get('Content-Disposition', '')
        );
    }

    #[Test]
    public function it_returns_404_for_unknown_commande_recu(): void
    {
        $eleveur = User::factory()->eleveur()->create(['is_verified' => true]);
        Sanctum::actingAs($eleveur);

        // getJson → 404 JSON (pas de redirect)
        $this->getJson('/api/eleveur/transactions/9999/recu')
            ->assertStatus(404);
    }

    #[Test]
    public function it_returns_403_for_other_eleveur_recu(): void
    {
        [$eleveur, , , $commande] = $this->createTransactionData();
        $other = User::factory()->eleveur()->create(['is_verified' => true]);
        Sanctum::actingAs($other);

        $this->getJson("/api/eleveur/transactions/{$commande->id}/recu")
            ->assertStatus(403);
    }

    #[Test]
    public function it_requires_authentication_for_recu(): void
    {
        [, , , $commande] = $this->createTransactionData();

        // getJson → Accept: application/json → 401 JSON (pas de redirect vers login)
        $this->getJson("/api/eleveur/transactions/{$commande->id}/recu")
            ->assertStatus(401);
    }
}