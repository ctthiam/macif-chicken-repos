<?php

namespace Tests\Feature\Paiement;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   PAY-01 — Initier paiement PayTech  (POST /api/paiements/initier)
 *   PAY-02 — Webhook HMAC              (POST /api/paiements/webhook)
 *   PAY-03 — Escrow : statut_paiement → 'paye'
 *
 * Fichier : tests/Feature/Paiement/PaiementTest.php
 * Lancer  : php artisan test --filter=PaiementTest
 */
class PaiementTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function createCommandeEnAttente(): array
    {
        $eleveur  = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true, 'is_active' => true]);

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'reserve',
        ]);

        $commande = Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'confirmee',
            'statut_paiement' => 'en_attente',
            'montant_total'   => 50000,
            'mode_paiement'   => 'wave',
        ]);

        return [$eleveur, $acheteur, $stock, $commande];
    }

    /**
     * Génère une signature HMAC valide pour les tests webhook.
     */
    private function hmacSignature(string $payload): string
    {
        $secret = config('services.paytech.api_secret', 'test_secret');
        return hash_hmac('sha256', $payload, $secret);
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-01 — POST /api/paiements/initier
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_initiates_payment_and_returns_url(): void
    {
        // Simuler la réponse PayTech
        Http::fake([
            '*paytech.sn*' => Http::response(['token' => 'tok_test_abc123'], 200),
        ]);

        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();
        Sanctum::actingAs($acheteur);

        $response = $this->postJson('/api/paiements/initier', [
            'commande_id' => $commande->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['payment_url', 'reference']);

        $this->assertStringContainsString('paytech.sn', $response->json('payment_url'));
        $this->assertStringContainsString('tok_test_abc123', $response->json('payment_url'));
    }

    #[Test]
    public function it_creates_paiement_record_on_initiation(): void
    {
        Http::fake(['*paytech.sn*' => Http::response(['token' => 'tok_abc'], 200)]);

        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();
        Sanctum::actingAs($acheteur);

        $this->postJson('/api/paiements/initier', ['commande_id' => $commande->id]);

        $this->assertDatabaseHas('paiements', [
            'commande_id' => $commande->id,
            'user_id'     => $acheteur->id,
            'montant'     => 50000,
            'statut'      => 'initie',
        ]);
    }

    #[Test]
    public function it_returns_502_when_paytech_fails(): void
    {
        Http::fake(['*paytech.sn*' => Http::response([], 500)]);

        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();
        Sanctum::actingAs($acheteur);

        $this->postJson('/api/paiements/initier', ['commande_id' => $commande->id])
            ->assertStatus(502)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_payment_for_non_confirmee_commande(): void
    {
        Http::fake(['*paytech.sn*' => Http::response(['token' => 'tok'], 200)]);

        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();
        $commande->update(['statut_commande' => 'en_preparation']);
        Sanctum::actingAs($acheteur);

        $this->postJson('/api/paiements/initier', ['commande_id' => $commande->id])
            ->assertStatus(422);
    }

    #[Test]
    public function it_rejects_payment_already_paye(): void
    {
        Http::fake(['*paytech.sn*' => Http::response(['token' => 'tok'], 200)]);

        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();
        $commande->update(['statut_paiement' => 'paye']);
        Sanctum::actingAs($acheteur);

        $this->postJson('/api/paiements/initier', ['commande_id' => $commande->id])
            ->assertStatus(422);
    }

    #[Test]
    public function it_returns_403_for_other_acheteur(): void
    {
        Http::fake(['*paytech.sn*' => Http::response(['token' => 'tok'], 200)]);

        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();
        $other = User::factory()->acheteur()->create(['is_verified' => true]);
        Sanctum::actingAs($other);

        $this->postJson('/api/paiements/initier', ['commande_id' => $commande->id])
            ->assertStatus(403);
    }

    #[Test]
    public function it_requires_authentication_for_initier(): void
    {
        [, , , $commande] = $this->createCommandeEnAttente();

        $this->postJson('/api/paiements/initier', ['commande_id' => $commande->id])
            ->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-02 — Webhook HMAC + PAY-03 — Escrow
    // POST /api/paiements/webhook
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function webhook_confirms_payment_with_valid_signature(): void
    {
        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();

        // Créer un paiement 'initie' en DB
        $paiement = Paiement::create([
            'commande_id'           => $commande->id,
            'user_id'               => $acheteur->id,
            'montant'               => 50000,
            'methode'               => 'wave',
            'reference_transaction' => 'MACIF-' . $commande->id . '-TEST001',
            'statut'                => 'initie',
        ]);

        $payload = json_encode([
            'type_event'  => 'sale_complete',
            'ref_command' => $paiement->reference_transaction,
            'montant'     => 50000,
        ]);

        $signature = $this->hmacSignature($payload);

        $response = $this->postJson('/api/paiements/webhook',
            json_decode($payload, true),
            ['X-PayTech-Signature' => $signature]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // PAY-03 : paiement = 'confirme', commande = 'paye'
        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'confirme',
        ]);

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'paye',
        ]);
    }

    #[Test]
    public function webhook_rejects_invalid_hmac_signature(): void
    {
        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();

        $payload = json_encode([
            'type_event'  => 'sale_complete',
            'ref_command' => 'MACIF-FAKE',
        ]);

        $response = $this->postJson('/api/paiements/webhook',
            json_decode($payload, true),
            ['X-PayTech-Signature' => 'signature_invalide_xxxxxx']
        );

        // PayTech attend toujours 200 même en cas d'erreur
        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function webhook_ignores_non_sale_complete_events(): void
    {
        $payload = json_encode([
            'type_event'  => 'sale_cancelled',
            'ref_command' => 'MACIF-123-TEST',
        ]);

        $signature = $this->hmacSignature($payload);

        $response = $this->postJson('/api/paiements/webhook',
            json_decode($payload, true),
            ['X-PayTech-Signature' => $signature]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Événement ignoré.']);
    }

    #[Test]
    public function webhook_is_idempotent_on_duplicate(): void
    {
        [$e, $acheteur, , $commande] = $this->createCommandeEnAttente();

        $paiement = Paiement::create([
            'commande_id'           => $commande->id,
            'user_id'               => $acheteur->id,
            'montant'               => 50000,
            'methode'               => 'wave',
            'reference_transaction' => 'MACIF-DUP-001',
            'statut'                => 'confirme', // déjà confirmé
        ]);
        $commande->update(['statut_paiement' => 'paye']);

        $payload = json_encode([
            'type_event'  => 'sale_complete',
            'ref_command' => 'MACIF-DUP-001',
        ]);

        $signature = $this->hmacSignature($payload);

        // Second appel webhook — doit être silencieux
        $response = $this->postJson('/api/paiements/webhook',
            json_decode($payload, true),
            ['X-PayTech-Signature' => $signature]
        );

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Paiement toujours 'confirme', pas de doublon
        $this->assertEquals(1, Paiement::where('reference_transaction', 'MACIF-DUP-001')->count());
    }

    #[Test]
    public function webhook_accessible_without_authentication(): void
    {
        $payload = json_encode(['type_event' => 'sale_complete', 'ref_command' => 'TEST']);
        $signature = $this->hmacSignature($payload);

        // Sans Sanctum::actingAs — route publique
        $this->postJson('/api/paiements/webhook',
            json_decode($payload, true),
            ['X-PayTech-Signature' => $signature]
        )->assertStatus(200);
    }
}