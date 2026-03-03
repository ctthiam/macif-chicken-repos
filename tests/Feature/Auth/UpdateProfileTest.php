<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature : AUTH-07 — Modification de profil
 * PUT /api/auth/profile
 *
 * Fichier : tests/Feature/Auth/UpdateProfileTest.php
 * Lancer  : php artisan test --filter=UpdateProfileTest
 */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Crée un utilisateur authentifié via Sanctum et retourne-le.
     */
    private function actingAsVerifiedUser(string $role = 'eleveur'): User
    {
        $user = User::factory()->{$role}()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    // ──────────────────────────────────────────────────────────────
    // Tests champs texte
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_updates_name_successfully(): void
    {
        $user = $this->actingAsVerifiedUser();

        $response = $this->putJson('/api/auth/profile', [
            'name' => 'Nouveau Nom',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profil mis à jour avec succès.',
            ])
            ->assertJsonPath('data.name', 'Nouveau Nom');

        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'name' => 'Nouveau Nom',
        ]);
    }

    #[Test]
    public function it_updates_phone_successfully(): void
    {
        $user = $this->actingAsVerifiedUser();

        $response = $this->putJson('/api/auth/profile', [
            'phone' => '+221779999888',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone', '+221779999888');

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'phone' => '+221779999888',
        ]);
    }

    #[Test]
    public function it_updates_ville_and_adresse(): void
    {
        $user = $this->actingAsVerifiedUser();

        $response = $this->putJson('/api/auth/profile', [
            'ville'   => 'Dakar',
            'adresse' => 'Plateau, Rue 10',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'      => $user->id,
            'ville'   => 'Dakar',
            'adresse' => 'Plateau, Rue 10',
        ]);
    }

    #[Test]
    public function it_can_update_multiple_fields_at_once(): void
    {
        $user = $this->actingAsVerifiedUser();

        $response = $this->putJson('/api/auth/profile', [
            'name'  => 'Fatou Sow',
            'ville' => 'Thiès',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Fatou Sow')
            ->assertJsonPath('data.ville', 'Thiès');
    }

    #[Test]
    public function it_does_nothing_when_no_fields_sent(): void
    {
        $user = $this->actingAsVerifiedUser();
        $originalName = $user->name;

        $response = $this->putJson('/api/auth/profile', []);

        // Doit quand même retourner 200 avec les données actuelles
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.name', $originalName);
    }

    // ──────────────────────────────────────────────────────────────
    // Tests upload avatar
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_uploads_avatar_successfully(): void
    {
        // Utiliser le disk 'public' (fake) pour les tests
        Storage::fake('public');

        $user = $this->actingAsVerifiedUser();

        $fakeImage = UploadedFile::fake()->image('photo.jpg', 200, 200);

        $response = $this->putJson('/api/auth/profile', [
            'avatar' => $fakeImage,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // L'avatar doit être renseigné dans la réponse
        $avatarUrl = $response->json('data.avatar');
        $this->assertNotNull($avatarUrl);
        $this->assertStringContainsString('avatar_', $avatarUrl);

        // Le fichier doit exister sur le disk fake
        $this->assertDatabaseMissing('users', [
            'id'     => $user->id,
            'avatar' => null,
        ]);
    }

    #[Test]
    public function it_rejects_non_image_file(): void
    {
        $user = $this->actingAsVerifiedUser();

        $fakeFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->putJson('/api/auth/profile', [
            'avatar' => $fakeFile,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['avatar']);
    }

    #[Test]
    public function it_rejects_avatar_over_2mb(): void
    {
        $user = $this->actingAsVerifiedUser();

        // Image de 3Mo — dépasse la limite de 2Mo
        $largeImage = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response = $this->putJson('/api/auth/profile', [
            'avatar' => $largeImage,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    // ──────────────────────────────────────────────────────────────
    // Tests validations
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_rejects_phone_already_used_by_another_user(): void
    {
        // Créer un autre user avec ce téléphone
        User::factory()->acheteur()->create(['phone' => '+221771111111']);

        $user = $this->actingAsVerifiedUser();

        $response = $this->putJson('/api/auth/profile', [
            'phone' => '+221771111111', // déjà pris
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    public function it_allows_user_to_keep_their_own_phone(): void
    {
        $user = $this->actingAsVerifiedUser();
        $ownPhone = $user->phone;

        // Envoyer son propre numéro — ne doit pas déclencher l'erreur unique
        $response = $this->putJson('/api/auth/profile', [
            'phone' => $ownPhone,
            'name'  => 'Nouveau Nom',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function it_rejects_invalid_phone_format(): void
    {
        $user = $this->actingAsVerifiedUser();

        $response = $this->putJson('/api/auth/profile', [
            'phone' => 'abc123', // format invalide
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    // ──────────────────────────────────────────────────────────────
    // Tests auth
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->putJson('/api/auth/profile', [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    }
}