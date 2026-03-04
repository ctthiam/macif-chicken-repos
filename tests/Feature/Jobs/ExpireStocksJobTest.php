<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExpireStocksJob;
use App\Models\Abonnement;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature : STK-07 — Expiration automatique des stocks
 *
 * Fichier : tests/Feature/Jobs/ExpireStocksJobTest.php
 * Lancer  : php artisan test --filter=ExpireStocksJobTest
 */
class ExpireStocksJobTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper : crée un éleveur avec abonnement actif ──────────

    private function createEleveurWithAbonnement(string $plan = 'pro'): User
    {
        $eleveur = User::factory()->eleveur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);

        Abonnement::create([
            'eleveur_id'         => $eleveur->id,
            'plan'               => $plan,
            'prix_mensuel'       => Abonnement::PRIX[$plan],
            'date_debut'         => now()->toDateString(),
            'date_fin'           => now()->addMonth()->toDateString(),
            'statut'             => 'actif',
            'methode_paiement'   => 'wave',
            'reference_paiement' => 'REF-' . $eleveur->id,
        ]);

        return $eleveur;
    }

    // ══════════════════════════════════════════════════════════════
    // Tests expiration par date_peremption_stock (STK-07)
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_expires_stocks_with_past_peremption_date(): void
    {
        $eleveur = $this->createEleveurWithAbonnement();

        // Stock périmé depuis hier
        $stockPerime = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'disponible',
            'date_peremption_stock' => now()->subDay()->toDateString(),
        ]);

        dispatch(new ExpireStocksJob());

        $this->assertDatabaseHas('stocks', [
            'id'     => $stockPerime->id,
            'statut' => 'expire',
        ]);
    }

    #[Test]
    public function it_does_not_expire_stocks_with_future_peremption_date(): void
    {
        $eleveur = $this->createEleveurWithAbonnement();

        $stockValide = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'disponible',
            'date_peremption_stock' => now()->addWeek()->toDateString(),
        ]);

        dispatch(new ExpireStocksJob());

        $this->assertDatabaseHas('stocks', [
            'id'     => $stockValide->id,
            'statut' => 'disponible',
        ]);
    }

    #[Test]
    public function it_does_not_expire_stocks_without_peremption_date(): void
    {
        $eleveur = $this->createEleveurWithAbonnement();

        $stockSansDate = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'disponible',
            'date_peremption_stock' => null,
        ]);

        dispatch(new ExpireStocksJob());

        $this->assertDatabaseHas('stocks', [
            'id'     => $stockSansDate->id,
            'statut' => 'disponible',
        ]);
    }

    #[Test]
    public function it_does_not_re_expire_already_expired_stocks(): void
    {
        $eleveur = $this->createEleveurWithAbonnement();

        $stockDejaExpire = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'expire',
            'date_peremption_stock' => now()->subWeek()->toDateString(),
        ]);

        dispatch(new ExpireStocksJob());

        // Toujours expire, pas de doublon ou d'erreur
        $this->assertDatabaseHas('stocks', [
            'id'     => $stockDejaExpire->id,
            'statut' => 'expire',
        ]);
    }

    #[Test]
    public function it_expires_both_disponible_and_reserve_by_date(): void
    {
        $eleveur = $this->createEleveurWithAbonnement();

        $disponible = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'disponible',
            'date_peremption_stock' => now()->subDay()->toDateString(),
        ]);

        $reserve = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'reserve',
            'date_peremption_stock' => now()->subDay()->toDateString(),
        ]);

        dispatch(new ExpireStocksJob());

        $this->assertDatabaseHas('stocks', ['id' => $disponible->id, 'statut' => 'expire']);
        $this->assertDatabaseHas('stocks', ['id' => $reserve->id,    'statut' => 'expire']);
    }

    #[Test]
    public function it_does_not_expire_epuise_stocks_by_date(): void
    {
        $eleveur = $this->createEleveurWithAbonnement();

        $epuise = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'epuise',
            'date_peremption_stock' => now()->subDay()->toDateString(),
        ]);

        dispatch(new ExpireStocksJob());

        // Épuisé reste épuisé — pas touché
        $this->assertDatabaseHas('stocks', [
            'id'     => $epuise->id,
            'statut' => 'epuise',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // Tests expiration par abonnement expiré
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_expires_stocks_of_eleveur_without_abonnement(): void
    {
        // Éleveur sans aucun abonnement
        $eleveur = User::factory()->eleveur()->create(['is_verified' => true]);

        $stock = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'disponible',
            'date_peremption_stock' => null,
        ]);

        dispatch(new ExpireStocksJob());

        $this->assertDatabaseHas('stocks', [
            'id'     => $stock->id,
            'statut' => 'expire',
        ]);
    }

    #[Test]
    public function it_expires_stocks_of_eleveur_with_expired_abonnement(): void
    {
        $eleveur = User::factory()->eleveur()->create(['is_verified' => true]);

        // Abonnement expiré
        Abonnement::create([
            'eleveur_id'         => $eleveur->id,
            'plan'               => 'pro',
            'prix_mensuel'       => Abonnement::PRIX['pro'],
            'date_debut'         => now()->subMonth()->toDateString(),
            'date_fin'           => now()->subDay()->toDateString(),
            'statut'             => 'expire',
            'methode_paiement'   => 'wave',
            'reference_paiement' => 'REF-EXP',
        ]);

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        dispatch(new ExpireStocksJob());

        $this->assertDatabaseHas('stocks', [
            'id'     => $stock->id,
            'statut' => 'expire',
        ]);
    }

    #[Test]
    public function it_preserves_stocks_of_eleveur_with_active_abonnement(): void
    {
        $eleveur = $this->createEleveurWithAbonnement('pro');

        $stock = Stock::factory()->create([
            'eleveur_id'            => $eleveur->id,
            'statut'                => 'disponible',
            'date_peremption_stock' => null,
        ]);

        dispatch(new ExpireStocksJob());

        $this->assertDatabaseHas('stocks', [
            'id'     => $stock->id,
            'statut' => 'disponible',
        ]);
    }

    #[Test]
    public function it_handles_multiple_eleveurs_correctly(): void
    {
        // Éleveur 1 : abonnement actif, pas de péremption → stock intact
        $eleveur1 = $this->createEleveurWithAbonnement('pro');
        $stock1   = Stock::factory()->create([
            'eleveur_id'            => $eleveur1->id,
            'statut'                => 'disponible',
            'date_peremption_stock' => null,
        ]);

        // Éleveur 2 : sans abonnement → stock expiré
        $eleveur2 = User::factory()->eleveur()->create();
        $stock2   = Stock::factory()->create([
            'eleveur_id' => $eleveur2->id,
            'statut'     => 'disponible',
        ]);

        // Éleveur 3 : abonnement actif, péremption passée → stock expiré
        $eleveur3 = $this->createEleveurWithAbonnement('starter');
        $stock3   = Stock::factory()->create([
            'eleveur_id'            => $eleveur3->id,
            'statut'                => 'disponible',
            'date_peremption_stock' => now()->subDay()->toDateString(),
        ]);

        dispatch(new ExpireStocksJob());

        $this->assertDatabaseHas('stocks', ['id' => $stock1->id, 'statut' => 'disponible']);
        $this->assertDatabaseHas('stocks', ['id' => $stock2->id, 'statut' => 'expire']);
        $this->assertDatabaseHas('stocks', ['id' => $stock3->id, 'statut' => 'expire']);
    }

    #[Test]
    public function it_runs_without_error_when_no_stocks_exist(): void
    {
        // Aucun stock en base
        dispatch(new ExpireStocksJob());

        // Pas d'exception — job idempotent
        $this->assertTrue(true);
    }
}