<?php

namespace Tests\Feature\Paiement;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Stock;
use App\Models\User;
use App\Services\EscrowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   PAY-04 — Libération fonds éleveur  (EscrowService::liberer)
 *   PAY-05 — Remboursement acheteur    (EscrowService::rembourser)
 *
 * Fichier : tests/Feature/Paiement/EscrowServiceTest.php
 * Lancer  : php artisan test --filter=EscrowServiceTest
 */
class EscrowServiceTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper ──────────────────────────────────────────────────

    private function createCommandePaye(): array
    {
        $eleveur  = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true, 'is_active' => true]);

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'epuise',
        ]);

        $commande = Commande::factory()->create([
            'eleveur_id'       => $eleveur->id,
            'acheteur_id'      => $acheteur->id,
            'stock_id'         => $stock->id,
            'statut_commande'  => 'livree',
            'statut_paiement'  => 'paye',
            'montant_total'    => 50000,
            'montant_eleveur'  => 46500,
            'escrow_libere_at' => null,
        ]);

        $paiement = Paiement::create([
            'commande_id'           => $commande->id,
            'user_id'               => $acheteur->id,
            'montant'               => 50000,
            'methode'               => 'wave',
            'reference_transaction' => 'MACIF-' . $commande->id . '-PAY04TEST',
            'statut'                => 'confirme',
        ]);

        return [$eleveur, $acheteur, $stock, $commande, $paiement];
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-04 — EscrowService::liberer()
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_liberates_escrow_when_paytech_succeeds(): void
    {
        Http::fake([
            '*paytech.sn/api/payment/transfer*' => Http::response(['success' => true], 200),
        ]);

        [$eleveur, , , $commande] = $this->createCommandePaye();

        app(EscrowService::class)->liberer($commande);

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'libere',
        ]);
        $this->assertNotNull($commande->fresh()->escrow_libere_at);
    }

    #[Test]
    public function it_sets_escrow_libere_at_timestamp(): void
    {
        Http::fake([
            '*paytech.sn*' => Http::response(['success' => true], 200),
        ]);

        [, , , $commande] = $this->createCommandePaye();

        app(EscrowService::class)->liberer($commande);

        $fresh = $commande->fresh();
        $this->assertNotNull($fresh->escrow_libere_at);
        $this->assertTrue($fresh->escrow_libere_at->isToday());
    }

    #[Test]
    public function it_throws_when_statut_paiement_is_not_paye(): void
    {
        [, , , $commande] = $this->createCommandePaye();
        $commande->update(['statut_paiement' => 'en_attente']);

        $this->expectException(\Exception::class);

        app(EscrowService::class)->liberer($commande);
    }

    #[Test]
    public function it_throws_when_paytech_transfer_fails(): void
    {
        Http::fake([
            '*paytech.sn/api/payment/transfer*' => Http::response(['error' => 'Insufficient funds'], 500),
        ]);

        // On force une clé API non vide pour déclencher l'appel HTTP
        config(['services.paytech.api_key' => 'test_key_non_vide']);

        [, , , $commande] = $this->createCommandePaye();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Échec du virement PayTech');

        app(EscrowService::class)->liberer($commande);

        // La commande ne doit pas être modifiée
        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'paye',
        ]);
    }

    #[Test]
    public function it_liberates_without_paytech_call_when_no_api_key(): void
    {
        // Pas d'appel HTTP si api_key est vide (mode hors-ligne / test)
        Http::fake(); // aucun fake défini → plantrait si appelé

        config(['services.paytech.api_key' => '']);

        [, , , $commande] = $this->createCommandePaye();

        app(EscrowService::class)->liberer($commande);

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'libere',
        ]);

        Http::assertNothingSent();
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-05 — EscrowService::rembourser()
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_refunds_acheteur_when_paytech_succeeds(): void
    {
        Http::fake([
            '*paytech.sn/api/payment/refund*' => Http::response(['success' => true], 200),
        ]);

        config(['services.paytech.api_key' => 'test_key_non_vide']);

        [$e, $acheteur, , $commande, $paiement] = $this->createCommandePaye();
        $commande->update(['statut_commande' => 'annulee']);

        app(EscrowService::class)->rembourser($commande);

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'rembourse',
        ]);

        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'rembourse',
        ]);
    }

    #[Test]
    public function it_still_marks_rembourse_even_when_paytech_refund_fails(): void
    {
        // PayTech refund échoue mais la commande est quand même marquée remboursée
        // (remboursement manuel possible — on ne bloque pas le flux)
        Http::fake([
            '*paytech.sn/api/payment/refund*' => Http::response(['error' => 'timeout'], 500),
        ]);

        config(['services.paytech.api_key' => 'test_key_non_vide']);

        [, , , $commande] = $this->createCommandePaye();

        app(EscrowService::class)->rembourser($commande);

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'rembourse',
        ]);
    }

    #[Test]
    public function it_refunds_without_paytech_when_no_confirmed_paiement(): void
    {
        // Commande annulée AVANT paiement — pas de paiement 'confirme' en DB
        $eleveur  = User::factory()->eleveur()->create(['is_verified' => true]);
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true]);
        $stock    = Stock::factory()->create(['eleveur_id' => $eleveur->id]);

        $commande = Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'annulee',
            'statut_paiement' => 'en_attente', // jamais payé
            'montant_total'   => 30000,
        ]);

        Http::fake();
        config(['services.paytech.api_key' => '']);

        app(EscrowService::class)->rembourser($commande);

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'rembourse',
        ]);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_refunds_without_paytech_call_when_no_api_key(): void
    {
        Http::fake();
        config(['services.paytech.api_key' => '']);

        [, , , $commande] = $this->createCommandePaye();

        app(EscrowService::class)->rembourser($commande);

        $this->assertDatabaseHas('commandes', [
            'id'              => $commande->id,
            'statut_paiement' => 'rembourse',
        ]);

        Http::assertNothingSent();
    }
}