<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   AUTH-04 — Connexion (POST /api/auth/login)
 *   AUTH-05 — Déconnexion (POST /api/auth/logout)
 *   AUTH-06 — Profil connecté (GET /api/auth/me)
 *
 * Fichier : tests/Feature/Auth/LoginTest.php
 * Lancer  : php artisan test --filter=LoginTest
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // AUTH-04 — Connexion
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_logs_in_successfully_with_valid_credentials(): void
    {
        $user = User::factory()->eleveur()->create([
            'email'       => 'mamadou@macif.sn',
            'password'    => bcrypt('Secret@2026'),
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'mamadou@macif.sn',
            'password' => 'Secret@2026',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Connexion réussie.'])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role'],
                ],
            ]);

        // Token bien présent dans la réponse
        $this->assertNotEmpty($response->json('data.token'));

        // Cookie httpOnly présent
        $response->assertCookie('api_token');
    }

    #[Test]
    public function it_rejects_wrong_password(): void
    {
        User::factory()->eleveur()->create([
            'email'    => 'test@macif.sn',
            'password' => bcrypt('CorrectPassword'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@macif.sn',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect.',
            ]);
    }

    #[Test]
    public function it_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'inconnu@macif.sn',
            'password' => 'Secret@2026',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_suspended_account(): void
    {
        User::factory()->eleveur()->create([
            'email'       => 'suspendu@macif.sn',
            'password'    => bcrypt('Secret@2026'),
            'is_verified' => true,
            'is_active'   => false, // compte suspendu
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'suspendu@macif.sn',
            'password' => 'Secret@2026',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Votre compte a été suspendu. Contactez l\'administration.',
            ]);
    }

    #[Test]
    public function it_rejects_unverified_email(): void
    {
        User::factory()->eleveur()->unverified()->create([
            'email'     => 'nonverifie@macif.sn',
            'password'  => bcrypt('Secret@2026'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nonverifie@macif.sn',
            'password' => 'Secret@2026',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.',
            ]);
    }

    #[Test]
    public function it_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function it_revokes_old_tokens_on_new_login(): void
    {
        $user = User::factory()->eleveur()->create([
            'email'       => 'multi@macif.sn',
            'password'    => bcrypt('Secret@2026'),
            'is_verified' => true,
            'is_active'   => true,
        ]);

        // Simuler un ancien token existant
        $user->createToken('old-token');
        $this->assertCount(1, $user->tokens);

        // Nouvelle connexion
        $this->postJson('/api/auth/login', [
            'email'    => 'multi@macif.sn',
            'password' => 'Secret@2026',
        ])->assertStatus(200);

        // Il ne doit rester qu'un seul token (le nouveau)
        $this->assertCount(1, $user->fresh()->tokens);
    }

    #[Test]
    public function it_returns_eleveur_profile_in_login_response(): void
    {
        $user = User::factory()->eleveur()->create([
            'email'       => 'eleveur@macif.sn',
            'password'    => bcrypt('Secret@2026'),
            'is_verified' => true,
            'is_active'   => true,
        ]);
        $user->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'eleveur@macif.sn',
            'password' => 'Secret@2026',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.role', 'eleveur')
            ->assertJsonStructure([
                'data' => [
                    'user' => ['eleveur_profile'],
                ],
            ]);
    }

    // ──────────────────────────────────────────────────────────────
    // AUTH-05 — Déconnexion
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_logs_out_and_invalidates_token(): void
    {
        $user = User::factory()->eleveur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);

        // Utiliser Sanctum pour simuler un utilisateur authentifié
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Déconnexion réussie.',
            ]);

        // Tous les tokens doivent être révoqués
        $this->assertCount(0, $user->fresh()->tokens);

        // Le cookie doit être expiré
        $response->assertCookieExpired('api_token');
    }

    #[Test]
    public function it_returns_401_on_logout_without_token(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────
    // AUTH-06 — Profil connecté
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_authenticated_user_profile(): void
    {
        $user = User::factory()->acheteur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);
        $user->acheteurProfile()->create(['type' => 'restaurant']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'email', 'role',
                    'acheteur_profile' => ['type'],
                ],
            ]);
    }

    #[Test]
    public function it_returns_401_on_me_without_auth(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }
}