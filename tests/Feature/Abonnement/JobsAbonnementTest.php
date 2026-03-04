<?php

namespace Tests\Feature\Abonnement;

use App\Jobs\AlerteExpirationAbonnementJob;
use App\Jobs\ExpireStocksAbonnementJob;
use App\Models\Abonnement;
use App\Models\Notification;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   ABO-03 — Alerte expiration 5 jours avant  (AlerteExpirationAbonnementJob)
 *   ABO-04 — Suspension stocks si expiré      (ExpireStocksAbonnementJob)
 *   ABO-05 — Restriction publication plan     (POST /api/eleveur/stocks — checkAbonnementLimit)
 *
 * Fichier : tests/Feature/Abonnement/JobsAbonnementTest.php
 * Lancer  : php artisan test --filter=JobsAbonnementTest
 */
class JobsAbonnementTest extends TestCase
{
    use RefreshDatabase;

    private function eleveur(array $attrs = []): User
    {
        return User::factory()->eleveur()->create(array_merge(
            ['is_verified' => true, 'is_active' => true],
            $attrs
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-03 — AlerteExpirationAbonnementJob
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sends_notification_when_abonnement_expires_in_5_days(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'actif',
            'date_fin'   => now()->addDays(3),
        ]);

        app()->call([app(AlerteExpirationAbonnementJob::class), 'handle']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $eleveur->id,
            'type'    => 'subscription',
        ]);
    }

    #[Test]
    public function it_does_not_alert_when_expiration_is_far(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'actif',
            'date_fin'   => now()->addDays(20),
        ]);

        app()->call([app(AlerteExpirationAbonnementJob::class), 'handle']);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $eleveur->id,
            'type'    => 'subscription',
        ]);
    }

    #[Test]
    public function it_does_not_alert_expired_abonnements(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->expire()->create([
            'eleveur_id' => $eleveur->id,
        ]);

        app()->call([app(AlerteExpirationAbonnementJob::class), 'handle']);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $eleveur->id,
            'type'    => 'subscription',
        ]);
    }

    #[Test]
    public function it_sends_alert_to_multiple_eleveurs(): void
    {
        $eleveur1 = $this->eleveur();
        $eleveur2 = $this->eleveur();

        foreach ([$eleveur1->id, $eleveur2->id] as $id) {
            Abonnement::factory()->starter()->create([
                'eleveur_id' => $id,
                'statut'     => 'actif',
                'date_fin'   => now()->addDays(2),
            ]);
        }

        app()->call([app(AlerteExpirationAbonnementJob::class), 'handle']);

        $this->assertEquals(2, Notification::where('type', 'subscription')->count());
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-04 — ExpireStocksAbonnementJob
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_expires_stocks_when_abonnement_expires(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'actif',
            'date_fin'   => now()->subDay(), // expiré hier
        ]);

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        app()->call([app(ExpireStocksAbonnementJob::class), 'handle']);

        $this->assertDatabaseHas('stocks', [
            'id'     => $stock->id,
            'statut' => 'expire',
        ]);
    }

    #[Test]
    public function it_marks_expired_abonnement_as_expire(): void
    {
        $eleveur = $this->eleveur();
        $abonnement = Abonnement::factory()->pro()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'actif',
            'date_fin'   => now()->subDay(),
        ]);

        app()->call([app(ExpireStocksAbonnementJob::class), 'handle']);

        $this->assertDatabaseHas('abonnements', [
            'id'     => $abonnement->id,
            'statut' => 'expire',
        ]);
    }

    #[Test]
    public function it_does_not_expire_stocks_with_active_abonnement(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->actif()->create([
            'eleveur_id' => $eleveur->id,
        ]);

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        app()->call([app(ExpireStocksAbonnementJob::class), 'handle']);

        $this->assertDatabaseHas('stocks', [
            'id'     => $stock->id,
            'statut' => 'disponible', // inchangé
        ]);
    }

    #[Test]
    public function it_notifies_eleveur_when_stocks_are_expired(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'actif',
            'date_fin'   => now()->subDay(),
        ]);
        Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        app()->call([app(ExpireStocksAbonnementJob::class), 'handle']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $eleveur->id,
            'type'    => 'subscription',
        ]);
    }

    #[Test]
    public function it_notifies_eleveur_only_once_even_with_multiple_stocks(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'actif',
            'date_fin'   => now()->subDay(),
        ]);

        Stock::factory()->count(3)->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        app()->call([app(ExpireStocksAbonnementJob::class), 'handle']);

        // Une seule notification même pour 3 stocks
        $this->assertEquals(
            1,
            Notification::where('user_id', $eleveur->id)->where('type', 'subscription')->count()
        );
    }

    #[Test]
    public function it_does_not_expire_epuise_stocks(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'actif',
            'date_fin'   => now()->subDay(),
        ]);

        $stock = Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'epuise', // déjà epuise — pas touché
        ]);

        app()->call([app(ExpireStocksAbonnementJob::class), 'handle']);

        $this->assertDatabaseHas('stocks', [
            'id'     => $stock->id,
            'statut' => 'epuise',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-05 — Restriction publication selon plan
    // POST /api/eleveur/stocks
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_blocks_stock_creation_without_abonnement(): void
    {
        $eleveur = $this->eleveur();
        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/stocks', [
            'titre'               => 'Test',
            'description'         => 'Description suffisamment longue pour la validation.',
            'quantite_disponible' => 10,
            'poids_moyen_kg'      => 2.5,
            'prix_par_kg'         => 1500,
            'mode_vente'          => 'vivant',
            'date_disponibilite'  => now()->addDays(3)->toDateString(),
        ])->assertStatus(403)
          ->assertJsonPath('success', false);
    }

    #[Test]
    public function it_blocks_stock_creation_when_starter_limit_reached(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->starter()->actif()->create(['eleveur_id' => $eleveur->id]);

        // Créer 3 stocks (limite starter)
        Stock::factory()->count(3)->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/stocks', [
            'titre'               => 'Stock de trop',
            'description'         => 'Description suffisamment longue pour la validation.',
            'quantite_disponible' => 5,
            'poids_moyen_kg'      => 2.0,
            'prix_par_kg'         => 1200,
            'mode_vente'          => 'vivant',
            'date_disponibilite'  => now()->addDays(3)->toDateString(),
        ])->assertStatus(403)
          ->assertJsonPath('success', false);
    }

    #[Test]
    public function it_allows_stock_creation_within_pro_limit(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->actif()->create(['eleveur_id' => $eleveur->id]);

        // 5 stocks — sous la limite de 10
        Stock::factory()->count(5)->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/stocks', [
            'titre'               => 'Nouveau stock',
            'description'         => 'Poulets locaux',
            'quantite_disponible' => 20,
            'poids_moyen_kg'      => 2.5,
            'prix_par_kg'         => 1500,
            'mode_vente'          => 'vivant',
            'date_disponibilite'  => now()->addDays(3)->toDateString(),
        ])->assertStatus(201);
    }

    #[Test]
    public function it_allows_unlimited_stocks_for_premium(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->premium()->actif()->create(['eleveur_id' => $eleveur->id]);

        // 15 stocks — pas de limite pour premium
        Stock::factory()->count(15)->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);

        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/stocks', [
            'titre'               => 'Stock premium illimité',
            'description'         => 'Poulets premium',
            'quantite_disponible' => 50,
            'poids_moyen_kg'      => 3.0,
            'prix_par_kg'         => 2000,
            'mode_vente'          => 'vivant',
            'date_disponibilite'  => now()->addDays(3)->toDateString(),
        ])->assertStatus(201);
    }

    #[Test]
    public function it_does_not_count_epuise_stocks_toward_limit(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->starter()->actif()->create(['eleveur_id' => $eleveur->id]);

        // 3 stocks épuisés — ne comptent pas pour la limite
        Stock::factory()->count(3)->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'epuise',
        ]);

        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/stocks', [
            'titre'               => 'Nouveau stock',
            'description'         => 'Poulets frais',
            'quantite_disponible' => 10,
            'poids_moyen_kg'      => 2.0,
            'prix_par_kg'         => 1300,
            'mode_vente'          => 'vivant',
            'date_disponibilite'  => now()->addDays(3)->toDateString(),
        ])->assertStatus(201);
    }
}