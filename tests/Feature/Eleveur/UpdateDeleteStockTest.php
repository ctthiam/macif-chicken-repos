<?php

namespace Tests\Feature\Eleveur;

use App\Models\Commande;
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
 *   STK-03 — Modifier une annonce  (PUT /api/eleveur/stocks/{id})
 *   STK-04 — Supprimer une annonce (DELETE /api/eleveur/stocks/{id})
 *
 * Fichier : tests/Feature/Eleveur/UpdateDeleteStockTest.php
 * Lancer  : php artisan test --filter=UpdateDeleteStockTest
 */
class UpdateDeleteStockTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function actingAsEleveur(): User
    {
        $user = User::factory()->eleveur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function createStock(User $user, array $overrides = []): Stock
    {
        return Stock::factory()->create(array_merge([
            'eleveur_id' => $user->id,
            'statut'     => 'disponible',
        ], $overrides));
    }

    // ══════════════════════════════════════════════════════════════
    // STK-03 — PUT /api/eleveur/stocks/{id}
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_updates_stock_titre(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user);

        $response = $this->putJson("/api/eleveur/stocks/{$stock->id}", [
            'titre' => 'Nouveau titre poulets fermiers',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.titre', 'Nouveau titre poulets fermiers');

        $this->assertDatabaseHas('stocks', [
            'id'    => $stock->id,
            'titre' => 'Nouveau titre poulets fermiers',
        ]);
    }

    #[Test]
    public function it_updates_multiple_fields_at_once(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user);

        $response = $this->putJson("/api/eleveur/stocks/{$stock->id}", [
            'prix_par_kg'         => 3000,
            'quantite_disponible' => 100,
            'mode_vente'          => 'abattu',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.prix_par_kg', '3000')
            ->assertJsonPath('data.quantite_disponible', 100)
            ->assertJsonPath('data.mode_vente', 'abattu');
    }

    #[Test]
    public function it_does_nothing_when_no_fields_sent(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user);

        $response = $this->putJson("/api/eleveur/stocks/{$stock->id}", []);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.titre', $stock->titre);
    }

    #[Test]
    public function it_returns_404_for_unknown_stock(): void
    {
        $this->actingAsEleveur();

        $this->putJson('/api/eleveur/stocks/9999', ['titre' => 'Test'])
            ->assertStatus(404);
    }

    #[Test]
    public function it_returns_403_when_updating_another_eleveur_stock(): void
    {
        $user  = $this->actingAsEleveur();
        $other = User::factory()->eleveur()->create();

        $stock = $this->createStock($other);

        $this->putJson("/api/eleveur/stocks/{$stock->id}", ['titre' => 'Hack'])
            ->assertStatus(403);
    }

    #[Test]
    public function it_cannot_update_epuise_stock(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user, ['statut' => 'epuise']);

        $this->putJson("/api/eleveur/stocks/{$stock->id}", ['titre' => 'Nouveau'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_cannot_update_expire_stock(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user, ['statut' => 'expire']);

        $this->putJson("/api/eleveur/stocks/{$stock->id}", ['titre' => 'Nouveau'])
            ->assertStatus(422);
    }

    #[Test]
    public function it_can_update_reserve_stock(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user, ['statut' => 'reserve']);

        $response = $this->putJson("/api/eleveur/stocks/{$stock->id}", [
            'prix_par_kg' => 2800,
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function it_rejects_invalid_mode_vente_on_update(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user);

        $this->putJson("/api/eleveur/stocks/{$stock->id}", ['mode_vente' => 'invalide'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode_vente']);
    }

    #[Test]
    public function it_adds_photos_on_update(): void
    {
        Storage::fake('public');
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user, ['photos' => []]);

        $response = $this->putJson("/api/eleveur/stocks/{$stock->id}", [
            'photos' => [UploadedFile::fake()->image('new.jpg')],
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.photos'));
    }

    #[Test]
    public function it_merges_new_photos_with_existing(): void
    {
        Storage::fake('public');
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user, [
            'photos' => ['http://storage.test/existing.jpg'],
        ]);

        $response = $this->putJson("/api/eleveur/stocks/{$stock->id}", [
            'photos' => [UploadedFile::fake()->image('new.jpg')],
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.photos'));
    }

    #[Test]
    public function it_requires_authentication_for_update(): void
    {
        $user  = User::factory()->eleveur()->create();
        $stock = $this->createStock($user);

        $this->putJson("/api/eleveur/stocks/{$stock->id}", ['titre' => 'Test'])
            ->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-04 — DELETE /api/eleveur/stocks/{id}
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_deletes_own_stock(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user);

        $response = $this->deleteJson("/api/eleveur/stocks/{$stock->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => null]);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    #[Test]
    public function it_returns_404_when_deleting_unknown_stock(): void
    {
        $this->actingAsEleveur();

        $this->deleteJson('/api/eleveur/stocks/9999')
            ->assertStatus(404);
    }

    #[Test]
    public function it_returns_403_when_deleting_another_eleveur_stock(): void
    {
        $user  = $this->actingAsEleveur();
        $other = User::factory()->eleveur()->create();
        $stock = $this->createStock($other);

        $this->deleteJson("/api/eleveur/stocks/{$stock->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function it_blocks_deletion_when_active_commandes_exist(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user);

        // Commande active liée à ce stock
        $acheteur = User::factory()->acheteur()->create();
        Commande::create([
            'acheteur_id'           => $acheteur->id,
            'eleveur_id'            => $user->id,
            'stock_id'              => $stock->id,
            'quantite'              => 5,
            'poids_total'           => 12.5,
            'montant_total'         => 31250,
            'commission_plateforme' => 2188,
            'montant_eleveur'       => 29062,
            'mode_paiement'         => 'wave',
            'statut_paiement'       => 'paye',
            'statut_commande'       => 'en_preparation',
            'adresse_livraison'     => 'Dakar, Plateau',
        ]);

        $response = $this->deleteJson("/api/eleveur/stocks/{$stock->id}");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertStringContainsString('commande', $response->json('message'));
        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
    }

    #[Test]
    public function it_allows_deletion_when_only_livree_commandes_exist(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user);

        $acheteur = User::factory()->acheteur()->create();
        Commande::create([
            'acheteur_id'           => $acheteur->id,
            'eleveur_id'            => $user->id,
            'stock_id'              => $stock->id,
            'quantite'              => 5,
            'poids_total'           => 12.5,
            'montant_total'         => 31250,
            'commission_plateforme' => 2188,
            'montant_eleveur'       => 29062,
            'mode_paiement'         => 'wave',
            'statut_paiement'       => 'libere',
            'statut_commande'       => 'livree', // pas active
            'adresse_livraison'     => 'Dakar',
        ]);

        $response = $this->deleteJson("/api/eleveur/stocks/{$stock->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    #[Test]
    public function it_deletes_epuise_stock(): void
    {
        $user  = $this->actingAsEleveur();
        $stock = $this->createStock($user, ['statut' => 'epuise']);

        $this->deleteJson("/api/eleveur/stocks/{$stock->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    #[Test]
    public function it_requires_authentication_for_delete(): void
    {
        $user  = User::factory()->eleveur()->create();
        $stock = $this->createStock($user);

        $this->deleteJson("/api/eleveur/stocks/{$stock->id}")
            ->assertStatus(401);
    }
}