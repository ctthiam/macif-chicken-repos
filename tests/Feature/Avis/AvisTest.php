<?php

namespace Tests\Feature\Avis;

use App\Models\Avis;
use App\Models\Commande;
use App\Models\EleveurProfile;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   AVI-01/02 — Laisser un avis          (POST /api/avis)
 *   AVI-03    — Réponse éleveur           (PUT /api/eleveur/avis/{id}/reply)
 *   AVI-04    — Recalcul note moyenne     (automatique après création)
 *   AVI-05    — Avis sur profil public    (GET /api/eleveurs/{id}/public)
 *   AVI-06    — Signalement avis abusif   (POST /api/avis/{id}/signaler)
 *
 * Fichier : tests/Feature/Avis/AvisTest.php
 * Lancer  : php artisan test --filter=AvisTest
 */
class AvisTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function eleveur(): User
    {
        $u = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $u->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test',
            'description'    => 'Belle ferme avicole de test.',
            'localisation'   => 'Dakar',
            'is_certified'   => false,
            'note_moyenne'   => 0.0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ]);
        return $u;
    }

    private function acheteur(): User
    {
        return User::factory()->acheteur()->create(['is_verified' => true, 'is_active' => true]);
    }

    private function commandeLivree(User $eleveur, User $acheteur): Commande
    {
        $stock = Stock::factory()->create(['eleveur_id' => $eleveur->id]);
        return Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'livree',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // AVI-01 + AVI-02 — POST /api/avis
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_creates_avis_for_livree_commande(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        Sanctum::actingAs($acheteur);

        $response = $this->postJson('/api/avis', [
            'commande_id' => $commande->id,
            'note'        => 5,
            'commentaire' => 'Excellent produit, très satisfait de la commande.',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['id', 'note', 'commentaire', 'created_at']]);

        $this->assertDatabaseHas('avis', [
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 5,
        ]);
    }

    #[Test]
    public function it_validates_note_between_1_and_5(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        Sanctum::actingAs($acheteur);

        $this->postJson('/api/avis', [
            'commande_id' => $commande->id,
            'note'        => 6, // invalide
            'commentaire' => 'Bon produit reçu à temps.',
        ])->assertStatus(422);

        $this->postJson('/api/avis', [
            'commande_id' => $commande->id,
            'note'        => 0, // invalide
            'commentaire' => 'Bon produit reçu à temps.',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_rejects_avis_if_commande_not_livree(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $stock    = Stock::factory()->create(['eleveur_id' => $eleveur->id]);

        $commande = Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'en_preparation', // pas livrée
        ]);

        Sanctum::actingAs($acheteur);

        $this->postJson('/api/avis', [
            'commande_id' => $commande->id,
            'note'        => 4,
            'commentaire' => 'Produit correct mais livraison lente.',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_rejects_duplicate_avis_for_same_commande(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 4,
            'commentaire' => 'Premier avis laissé correctement.',
        ]);

        Sanctum::actingAs($acheteur);

        $this->postJson('/api/avis', [
            'commande_id' => $commande->id,
            'note'        => 3,
            'commentaire' => 'Tentative de second avis sur la même commande.',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_rejects_avis_for_other_acheteur_commande(): void
    {
        $eleveur   = $this->eleveur();
        $acheteur1 = $this->acheteur();
        $acheteur2 = $this->acheteur();
        $commande  = $this->commandeLivree($eleveur, $acheteur1);

        Sanctum::actingAs($acheteur2); // autre acheteur

        $this->postJson('/api/avis', [
            'commande_id' => $commande->id,
            'note'        => 5,
            'commentaire' => 'Tentative de vol d\'avis pour une autre commande.',
        ])->assertStatus(403);
    }

    #[Test]
    public function it_requires_commentaire_min_10_chars(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        Sanctum::actingAs($acheteur);

        $this->postJson('/api/avis', [
            'commande_id' => $commande->id,
            'note'        => 3,
            'commentaire' => 'Court', // < 10 chars
        ])->assertStatus(422);
    }

    #[Test]
    public function it_requires_auth_to_post_avis(): void
    {
        $this->postJson('/api/avis', [
            'commande_id' => 1,
            'note'        => 4,
            'commentaire' => 'Test sans authentification.',
        ])->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    // AVI-04 — Recalcul note moyenne
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_recalculates_note_moyenne_after_avis(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();

        $commande1 = $this->commandeLivree($eleveur, $acheteur);
        $commande2 = $this->commandeLivree($eleveur, $this->acheteur());

        Sanctum::actingAs($acheteur);

        $this->postJson('/api/avis', [
            'commande_id' => $commande1->id,
            'note'        => 4,
            'commentaire' => 'Très bonne qualité, poulets bien nourris.',
        ]);

        $acheteur2 = User::factory()->acheteur()->create(['is_verified' => true]);
        Sanctum::actingAs($acheteur2);

        // Deuxième avis via un autre acheteur sur commande2
        Avis::recalculeNoteMoyenne($eleveur->id); // forcer après création directe
        Avis::create([
            'commande_id' => $commande2->id,
            'auteur_id'   => $acheteur2->id,
            'cible_id'    => $eleveur->id,
            'note'        => 2,
            'commentaire' => 'Qualité décevante pour le prix demandé.',
        ]);
        Avis::recalculeNoteMoyenne($eleveur->id);

        $profile = $eleveur->eleveurProfile()->first();
        // AVG(4, 2) = 3.0
        $this->assertEquals(3.0, (float) $profile->note_moyenne);
        $this->assertEquals(2, $profile->nombre_avis);
    }

    #[Test]
    public function it_updates_note_moyenne_immediately_after_post(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        Sanctum::actingAs($acheteur);

        $this->postJson('/api/avis', [
            'commande_id' => $commande->id,
            'note'        => 5,
            'commentaire' => 'Parfait, rien à redire sur la qualité.',
        ]);

        $profile = $eleveur->eleveurProfile()->first();
        $this->assertEquals(5.0, (float) $profile->note_moyenne);
        $this->assertEquals(1,   $profile->nombre_avis);
    }

    // ══════════════════════════════════════════════════════════════
    // AVI-03 — PUT /api/eleveur/avis/{id}/reply
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_allows_eleveur_to_reply_to_avis(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        $avis = Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 3,
            'commentaire' => 'Produit correct mais délai un peu long.',
        ]);

        Sanctum::actingAs($eleveur);

        $response = $this->putJson("/api/eleveur/avis/{$avis->id}/reply", [
            'reply' => 'Merci pour votre retour, nous améliorons nos délais de livraison.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('avis', [
            'id'    => $avis->id,
            'reply' => 'Merci pour votre retour, nous améliorons nos délais de livraison.',
        ]);
    }

    #[Test]
    public function it_rejects_reply_from_other_eleveur(): void
    {
        $eleveur  = $this->eleveur();
        $other    = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        $avis = Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 4,
            'commentaire' => 'Bonne qualité, je recommande cet éleveur.',
        ]);

        Sanctum::actingAs($other);

        $this->putJson("/api/eleveur/avis/{$avis->id}/reply", [
            'reply' => 'Tentative de réponse non autorisée sur cet avis.',
        ])->assertStatus(403);
    }

    #[Test]
    public function it_requires_reply_min_10_chars(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        $avis = Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 3,
            'commentaire' => 'Qualité correcte pour le prix pratiqué.',
        ]);

        Sanctum::actingAs($eleveur);

        $this->putJson("/api/eleveur/avis/{$avis->id}/reply", [
            'reply' => 'Merci', // < 10 chars
        ])->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════
    // AVI-05 — GET /api/eleveurs/{id}/public (avis inclus)
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_includes_avis_in_eleveur_public_profile(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 5,
            'commentaire' => 'Excellents poulets, très frais et bien préparés.',
        ]);

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        $response->assertStatus(200);
        $this->assertArrayHasKey('avis', $response->json('data'));
    }

    #[Test]
    public function it_excludes_reported_avis_from_public_profile(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 1,
            'commentaire' => 'Avis abusif signalé et retiré du profil public.',
            'is_reported' => true, // signalé
        ]);

        $response = $this->getJson("/api/eleveurs/{$eleveur->id}/public");

        $avis = $response->json('data.avis');
        $this->assertCount(0, $avis);
    }

    // ══════════════════════════════════════════════════════════════
    // AVI-06 — POST /api/avis/{id}/signaler
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_reports_an_avis(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        $avis = Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 1,
            'commentaire' => 'Contenu potentiellement abusif ou trompeur.',
        ]);

        Sanctum::actingAs($acheteur);

        $this->postJson("/api/avis/{$avis->id}/signaler")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('avis', [
            'id'          => $avis->id,
            'is_reported' => true,
        ]);
    }

    #[Test]
    public function it_recalculates_note_after_report(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commandeLivree($eleveur, $acheteur);

        $avis = Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $eleveur->id,
            'note'        => 1,
            'commentaire' => 'Avis abusif qui sera signalé et exclu du calcul.',
        ]);

        // Forcer note_moyenne = 1 avant signalement
        Avis::recalculeNoteMoyenne($eleveur->id);
        $this->assertEquals(1.0, (float) $eleveur->eleveurProfile()->first()->note_moyenne);

        Sanctum::actingAs($acheteur);
        $this->postJson("/api/avis/{$avis->id}/signaler");

        // Après signalement : plus d'avis valides → note = 0
        $this->assertEquals(0.0, (float) $eleveur->eleveurProfile()->first()->note_moyenne);
    }

    #[Test]
    public function it_returns_404_for_unknown_avis_signaler(): void
    {
        $acheteur = $this->acheteur();
        Sanctum::actingAs($acheteur);

        $this->postJson('/api/avis/9999/signaler')->assertStatus(404);
    }

    #[Test]
    public function it_requires_auth_to_signaler(): void
    {
        $this->postJson('/api/avis/1/signaler')->assertStatus(401);
    }
}