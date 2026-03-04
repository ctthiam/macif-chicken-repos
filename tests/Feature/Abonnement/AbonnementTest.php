<?php

namespace Tests\Feature\Abonnement;

use App\Models\Abonnement;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   ABO-01 — Plans disponibles     (GET /api/abonnements/plans)
 *   ABO-02 — Souscrire PayTech     (POST /api/eleveur/abonnement/souscrire)
 *   ABO-06 — Page gestion          (GET /api/eleveur/abonnement)
 *
 * Fichier : tests/Feature/Abonnement/AbonnementTest.php
 * Lancer  : php artisan test --filter=AbonnementTest
 */
class AbonnementTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function eleveur(array $attrs = []): User
    {
        return User::factory()->eleveur()->create(array_merge(
            ['is_verified' => true, 'is_active' => true],
            $attrs
        ));
    }

    private function hmac(string $payload): string
    {
        return hash_hmac('sha256', $payload, config('services.paytech.api_secret', 'test_secret'));
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-01 — GET /api/abonnements/plans  (public)
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_all_plans_publicly(): void
    {
        $response = $this->getJson('/api/abonnements/plans');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    ['plan', 'prix', 'stocks_max', 'description'],
                ],
            ]);

        $plans = collect($response->json('data'))->pluck('plan')->toArray();
        $this->assertContains('starter', $plans);
        $this->assertContains('pro', $plans);
        $this->assertContains('premium', $plans);
    }

    #[Test]
    public function it_returns_correct_plan_prices(): void
    {
        $response = $this->getJson('/api/abonnements/plans');

        $data = collect($response->json('data'))->keyBy('plan');

        $this->assertEquals(5000,  $data['starter']['prix']);
        $this->assertEquals(15000, $data['pro']['prix']);
        $this->assertEquals(30000, $data['premium']['prix']);
    }

    #[Test]
    public function it_returns_correct_stock_limits(): void
    {
        $response = $this->getJson('/api/abonnements/plans');

        $data = collect($response->json('data'))->keyBy('plan');

        $this->assertEquals(3,    $data['starter']['stocks_max']);
        $this->assertEquals(10,   $data['pro']['stocks_max']);
        $this->assertNull($data['premium']['stocks_max']); // illimité
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-06 — GET /api/eleveur/abonnement
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_active_abonnement_for_eleveur(): void
    {
        $eleveur    = $this->eleveur();
        $abonnement = Abonnement::factory()->pro()->actif()->create([
            'eleveur_id' => $eleveur->id,
        ]);

        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/abonnement');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.plan', 'pro')
            ->assertJsonPath('data.statut', 'actif')
            ->assertJsonStructure(['data' => ['id', 'plan', 'prix_mensuel', 'date_fin', 'jours_restants', 'est_actif']]);
    }

    #[Test]
    public function it_returns_null_data_when_no_active_abonnement(): void
    {
        $eleveur = $this->eleveur();
        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/abonnement');

        $response->assertStatus(200)
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['plans']);
    }

    #[Test]
    public function it_returns_stocks_actifs_count(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->actif()->create(['eleveur_id' => $eleveur->id]);

        // Créer quelques stocks actifs
        Stock::factory()->count(2)->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'disponible',
        ]);
        Stock::factory()->create([
            'eleveur_id' => $eleveur->id,
            'statut'     => 'epuise', // pas compté
        ]);

        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/abonnement');

        $this->assertEquals(2, $response->json('stocks_actifs'));
    }

    #[Test]
    public function it_ignores_expired_abonnement_in_show(): void
    {
        $eleveur = $this->eleveur();
        Abonnement::factory()->pro()->expire()->create(['eleveur_id' => $eleveur->id]);

        Sanctum::actingAs($eleveur);

        $response = $this->getJson('/api/eleveur/abonnement');

        $response->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function it_requires_auth_for_show(): void
    {
        $this->getJson('/api/eleveur/abonnement')->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-02 — POST /api/eleveur/abonnement/souscrire
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_creates_abonnement_suspendu_and_returns_payment_url(): void
    {
        Http::fake([
            '*paytech.sn*' => Http::response(['token' => 'tok_abo_test'], 200),
        ]);

        $eleveur = $this->eleveur();
        Sanctum::actingAs($eleveur);

        $response = $this->postJson('/api/eleveur/abonnement/souscrire', [
            'plan'             => 'pro',
            'methode_paiement' => 'wave',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['payment_url', 'reference', 'abonnement']);

        $this->assertStringContainsString('tok_abo_test', $response->json('payment_url'));
    }

    #[Test]
    public function it_stores_abonnement_as_suspendu_before_payment(): void
    {
        Http::fake(['*paytech.sn*' => Http::response(['token' => 'tok'], 200)]);

        $eleveur = $this->eleveur();
        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/abonnement/souscrire', [
            'plan'             => 'starter',
            'methode_paiement' => 'orange_money',
        ]);

        $this->assertDatabaseHas('abonnements', [
            'eleveur_id' => $eleveur->id,
            'plan'       => 'starter',
            'statut'     => 'suspendu',
            'prix_mensuel'=> 5000,
        ]);
    }

    #[Test]
    public function it_activates_abonnement_on_webhook(): void
    {
        $eleveur = $this->eleveur();

        $abonnement = Abonnement::factory()->pro()->create([
            'eleveur_id'         => $eleveur->id,
            'statut'             => 'suspendu',
            'reference_paiement' => 'ABO-WEBHOOK-TEST',
        ]);

        $payload = json_encode([
            'type_event'  => 'sale_complete',
            'ref_command' => 'ABO-WEBHOOK-TEST',
        ]);

        $signature = $this->hmac($payload);

        $this->postJson('/api/paiements/webhook-abonnement',
            json_decode($payload, true),
            ['X-PayTech-Signature' => $signature]
        )->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('abonnements', [
            'id'     => $abonnement->id,
            'statut' => 'actif',
        ]);
    }

    #[Test]
    public function it_is_idempotent_on_duplicate_webhook_abonnement(): void
    {
        $eleveur = $this->eleveur();
        $abonnement = Abonnement::factory()->pro()->actif()->create([
            'eleveur_id'         => $eleveur->id,
            'reference_paiement' => 'ABO-DUP-001',
        ]);

        $payload = json_encode([
            'type_event'  => 'sale_complete',
            'ref_command' => 'ABO-DUP-001',
        ]);
        $signature = $this->hmac($payload);

        // Second appel — déjà actif
        $this->postJson('/api/paiements/webhook-abonnement',
            json_decode($payload, true),
            ['X-PayTech-Signature' => $signature]
        )->assertStatus(200)->assertJson(['success' => true]);

        $this->assertEquals(1, Abonnement::where('reference_paiement', 'ABO-DUP-001')->count());
    }

    #[Test]
    public function it_rejects_invalid_plan_on_souscrire(): void
    {
        $eleveur = $this->eleveur();
        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/abonnement/souscrire', [
            'plan'             => 'gold', // invalide
            'methode_paiement' => 'wave',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_rejects_invalid_methode_paiement(): void
    {
        $eleveur = $this->eleveur();
        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/abonnement/souscrire', [
            'plan'             => 'pro',
            'methode_paiement' => 'visa', // invalide
        ])->assertStatus(422);
    }

    #[Test]
    public function it_requires_auth_for_souscrire(): void
    {
        $this->postJson('/api/eleveur/abonnement/souscrire', [
            'plan'             => 'pro',
            'methode_paiement' => 'wave',
        ])->assertStatus(401);
    }

    #[Test]
    public function it_schedules_renewal_from_current_date_fin(): void
    {
        Http::fake(['*paytech.sn*' => Http::response(['token' => 'tok'], 200)]);

        $eleveur = $this->eleveur();
        $actuel  = Abonnement::factory()->pro()->actif()->create([
            'eleveur_id' => $eleveur->id,
            'date_fin'   => now()->addDays(10),
        ]);

        Sanctum::actingAs($eleveur);

        $this->postJson('/api/eleveur/abonnement/souscrire', [
            'plan'             => 'premium',
            'methode_paiement' => 'wave',
        ])->assertStatus(200);

        // Le nouveau commence après l'actuel
        $nouveau = Abonnement::where('eleveur_id', $eleveur->id)
            ->where('plan', 'premium')
            ->first();

        $this->assertTrue(
            $nouveau->date_debut->gte($actuel->date_fin),
            'Le renouvellement doit débuter après la fin de l\'abonnement actuel.'
        );
    }
}