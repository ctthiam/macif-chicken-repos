<?php

namespace Tests\Feature\Eleveur;

use App\Models\Abonnement;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   STK-01 — Créer annonce de stock    (POST /api/eleveur/stocks)
 *   STK-02 — Upload photos du stock    (inclus dans STK-01)
 *   STK-06 — Liste de mes stocks       (GET /api/eleveur/stocks)
 *   STK-08 — Restriction abonnement   (inclus dans STK-01)
 *
 * Fichier : tests/Feature/Eleveur/CreateStockTest.php
 * Lancer  : php artisan test --filter=CreateStockTest
 */
class CreateStockTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function actingAsEleveur(): User
    {
        $user = User::factory()->eleveur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);
        $user->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test',
            'description'    => 'Test',
            'localisation'   => 'Dakar',
            'is_certified'   => false,
            'note_moyenne'   => 0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function withAbonnement(User $user, string $plan = 'pro'): Abonnement
    {
        return Abonnement::create([
            'eleveur_id'         => $user->id,
            'plan'               => $plan,
            'prix_mensuel'       => Abonnement::PRIX[$plan],
            'date_debut'         => now()->toDateString(),
            'date_fin'           => now()->addMonth()->toDateString(),
            'statut'             => 'actif',
            'methode_paiement'   => 'wave',
            'reference_paiement' => 'REF-TEST-001',
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'titre'               => 'Poulets fermiers bio de qualité',
            'description'         => 'Lot de poulets fermiers élevés en plein air, nourris au grain.',
            'quantite_disponible' => 50,
            'poids_moyen_kg'      => 2.5,
            'prix_par_kg'         => 2500,
            'mode_vente'          => 'vivant',
            'date_disponibilite'  => now()->addDay()->toDateString(),
        ], $overrides);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-01 — POST /api/eleveur/stocks
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_creates_a_stock_with_valid_data(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Annonce de stock créée avec succès.'])
            ->assertJsonStructure([
                'data' => [
                    'id', 'eleveur_id', 'titre', 'description',
                    'quantite_disponible', 'poids_moyen_kg', 'prix_par_kg',
                    'mode_vente', 'date_disponibilite', 'statut', 'vues', 'photos',
                ],
            ]);

        $this->assertDatabaseHas('stocks', [
            'eleveur_id' => $user->id,
            'titre'      => 'Poulets fermiers bio de qualité',
            'statut'     => 'disponible',
            'vues'       => 0,
        ]);
    }

    #[Test]
    public function it_creates_stock_with_all_optional_fields(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload([
            'prix_par_unite'        => 3500,
            'mode_vente'            => 'les_deux',
            'date_peremption_stock' => now()->addMonth()->toDateString(),
        ]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('stocks', [
            'eleveur_id'    => $user->id,
            'prix_par_unite'=> 3500,
            'mode_vente'    => 'les_deux',
        ]);
    }

    #[Test]
    public function it_sets_statut_disponible_and_vues_zero_by_default(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.statut', 'disponible')
            ->assertJsonPath('data.vues', 0);
    }

    #[Test]
    public function it_rejects_missing_required_fields(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $response = $this->postJson('/api/eleveur/stocks', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'titre', 'description', 'quantite_disponible',
                'poids_moyen_kg', 'prix_par_kg', 'mode_vente', 'date_disponibilite',
            ]);
    }

    #[Test]
    public function it_rejects_invalid_mode_vente(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload([
            'mode_vente' => 'kg', // invalide
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mode_vente']);
    }

    #[Test]
    public function it_rejects_past_date_disponibilite(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload([
            'date_disponibilite' => now()->subDay()->toDateString(),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_disponibilite']);
    }

    #[Test]
    public function it_rejects_peremption_before_disponibilite(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload([
            'date_disponibilite'    => now()->addWeek()->toDateString(),
            'date_peremption_stock' => now()->addDay()->toDateString(), // avant dispo
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_peremption_stock']);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson('/api/eleveur/stocks', $this->validPayload())
            ->assertStatus(401);
    }

    #[Test]
    public function it_returns_403_for_acheteur(): void
    {
        $acheteur = User::factory()->acheteur()->create(['is_verified' => true]);
        Sanctum::actingAs($acheteur);

        $this->postJson('/api/eleveur/stocks', $this->validPayload())
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-02 — Upload photos
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_uploads_photos_with_stock(): void
    {
        Storage::fake('public');
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $payload          = $this->validPayload();
        $payload['photos'] = [
            UploadedFile::fake()->image('poulet1.jpg', 800, 600),
            UploadedFile::fake()->image('poulet2.jpg', 800, 600),
        ];

        $response = $this->postJson('/api/eleveur/stocks', $payload);

        $response->assertStatus(201);

        $photos = $response->json('data.photos');
        $this->assertIsArray($photos);
        $this->assertCount(2, $photos);
    }

    #[Test]
    public function it_creates_stock_without_photos(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        $response->assertStatus(201);
        $this->assertEmpty($response->json('data.photos'));
    }

    #[Test]
    public function it_rejects_more_than_5_photos(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro');

        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = UploadedFile::fake()->image("photo{$i}.jpg");
        }

        $payload            = $this->validPayload();
        $payload['photos']  = $photos;

        $this->postJson('/api/eleveur/stocks', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-08 — Restriction selon abonnement
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_blocks_creation_without_abonnement(): void
    {
        $user = $this->actingAsEleveur();
        // Pas d'abonnement créé

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        $response->assertStatus(403)
            ->assertJson(['success' => false])
            ->assertJsonPath('errors.abonnement', 'Vous devez avoir un abonnement actif pour publier des annonces.');
    }

    #[Test]
    public function it_blocks_creation_when_starter_limit_reached(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'starter'); // max 3

        // Créer 3 stocks actifs
        Stock::factory()->count(3)->create([
            'eleveur_id' => $user->id,
            'statut'     => 'disponible',
        ]);

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        $response->assertStatus(403)
            ->assertJson(['success' => false]);

        $this->assertStringContainsString('Starter', $response->json('message'));
        $this->assertStringContainsString('3', $response->json('message'));
    }

    #[Test]
    public function it_blocks_creation_when_pro_limit_reached(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'pro'); // max 10

        Stock::factory()->count(10)->create([
            'eleveur_id' => $user->id,
            'statut'     => 'disponible',
        ]);

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        $response->assertStatus(403);
        $this->assertStringContainsString('Pro', $response->json('message'));
    }

    #[Test]
    public function it_allows_creation_for_premium_beyond_10(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'premium'); // illimité

        // 15 stocks actifs — doit quand même pouvoir créer
        Stock::factory()->count(15)->create([
            'eleveur_id' => $user->id,
            'statut'     => 'disponible',
        ]);

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        $response->assertStatus(201);
    }

    #[Test]
    public function it_does_not_count_epuise_stocks_in_limit(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'starter'); // max 3

        // 2 stocks actifs + 3 épuisés — la limite ne devrait pas être atteinte
        Stock::factory()->count(2)->create(['eleveur_id' => $user->id, 'statut' => 'disponible']);
        Stock::factory()->count(3)->create(['eleveur_id' => $user->id, 'statut' => 'epuise']);

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        // 2 actifs < 3 limite → doit passer
        $response->assertStatus(201);
    }

    #[Test]
    public function it_blocks_with_expired_abonnement(): void
    {
        $user = $this->actingAsEleveur();

        // Abonnement expiré
        Abonnement::create([
            'eleveur_id'         => $user->id,
            'plan'               => 'pro',
            'prix_mensuel'       => Abonnement::PRIX['pro'],
            'date_debut'         => now()->subMonth()->toDateString(),
            'date_fin'           => now()->subDay()->toDateString(), // expiré hier
            'statut'             => 'expire',
            'methode_paiement'   => 'wave',
            'reference_paiement' => 'REF-EXP-001',
        ]);

        $response = $this->postJson('/api/eleveur/stocks', $this->validPayload());

        $response->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-06 — GET /api/eleveur/stocks
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_paginated_stocks(): void
    {
        $user = $this->actingAsEleveur();
        $this->withAbonnement($user, 'premium');

        Stock::factory()->count(15)->create(['eleveur_id' => $user->id]);

        $response = $this->getJson('/api/eleveur/stocks');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        // 12 par page
        $this->assertCount(12, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    #[Test]
    public function it_returns_only_own_stocks(): void
    {
        $user  = $this->actingAsEleveur();
        $other = User::factory()->eleveur()->create();

        Stock::factory()->count(3)->create(['eleveur_id' => $user->id]);
        Stock::factory()->count(5)->create(['eleveur_id' => $other->id]);

        $response = $this->getJson('/api/eleveur/stocks');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_stocks_by_statut(): void
    {
        $user = $this->actingAsEleveur();

        Stock::factory()->count(3)->create(['eleveur_id' => $user->id, 'statut' => 'disponible']);
        Stock::factory()->count(2)->create(['eleveur_id' => $user->id, 'statut' => 'epuise']);

        $response = $this->getJson('/api/eleveur/stocks?statut=disponible');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    #[Test]
    public function it_returns_empty_list_when_no_stocks(): void
    {
        $this->actingAsEleveur();

        $response = $this->getJson('/api/eleveur/stocks');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
        $this->assertEquals(0, $response->json('meta.total'));
    }
}