<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendPasswordResetJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature : AUTH-08 — Réinitialisation de mot de passe
 *
 * Fichier : tests/Feature/Auth/ResetPasswordTest.php
 * Lancer  : php artisan test --filter=ResetPasswordTest
 */
class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // POST /api/auth/forgot-password
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_sends_reset_email_for_existing_user(): void
    {
        Queue::fake();

        $user = User::factory()->eleveur()->create([
            'email'       => 'mamadou@macif.sn',
            'is_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'mamadou@macif.sn',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Token généré en base
        $this->assertNotNull($user->fresh()->password_reset_token);
        $this->assertNotNull($user->fresh()->password_reset_expires_at);

        // Job dispatché
        Queue::assertPushed(SendPasswordResetJob::class, function ($job) {
            return $job->user->email === 'mamadou@macif.sn';
        });
    }

    #[Test]
    public function it_returns_same_response_for_unknown_email(): void
    {
        // Sécurité anti-énumération : même réponse si email inconnu
        Queue::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'inconnu@macif.sn',
        ]);

        // Toujours 200 — on ne révèle pas si l'email existe
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_token_expires_after_1_hour(): void
    {
        Queue::fake();

        $user = User::factory()->eleveur()->create(['is_verified' => true]);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(200);

        // Vérifier que l'expiration est bien dans ~1h
        $expiresAt = $user->fresh()->password_reset_expires_at;
        $this->assertNotNull($expiresAt);
        $this->assertTrue($expiresAt->isFuture());
        $this->assertTrue($expiresAt->diffInMinutes(now()) <= 61);
    }

    #[Test]
    public function it_requires_email_for_forgot_password(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/auth/reset-password
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_resets_password_with_valid_token(): void
    {
        $token = Str::random(64);

        $user = User::factory()->eleveur()->create([
            'is_verified'               => true,
            'password_reset_token'      => $token,
            'password_reset_expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewSecret@2026',
            'password_confirmation' => 'NewSecret@2026',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
            ]);

        // Mot de passe bien changé
        $this->assertTrue(Hash::check('NewSecret@2026', $user->fresh()->password));

        // Token effacé après usage
        $this->assertNull($user->fresh()->password_reset_token);
        $this->assertNull($user->fresh()->password_reset_expires_at);
    }

    #[Test]
    public function it_revokes_all_sanctum_tokens_after_reset(): void
    {
        $token = Str::random(64);

        $user = User::factory()->eleveur()->create([
            'is_verified'               => true,
            'password_reset_token'      => $token,
            'password_reset_expires_at' => now()->addHour(),
        ]);

        // Simuler un token Sanctum actif
        $user->createToken('active-session');
        $this->assertCount(1, $user->tokens);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewSecret@2026',
            'password_confirmation' => 'NewSecret@2026',
        ])->assertStatus(200);

        // Tous les tokens révoqués
        $this->assertCount(0, $user->fresh()->tokens);
    }

    #[Test]
    public function it_rejects_wrong_token(): void
    {
        $user = User::factory()->eleveur()->create([
            'is_verified'               => true,
            'password_reset_token'      => Str::random(64),
            'password_reset_expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => Str::random(64), // mauvais token
            'password'              => 'NewSecret@2026',
            'password_confirmation' => 'NewSecret@2026',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_rejects_expired_token(): void
    {
        $token = Str::random(64);

        $user = User::factory()->eleveur()->create([
            'is_verified'               => true,
            'password_reset_token'      => $token,
            'password_reset_expires_at' => now()->subHour(), // expiré
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewSecret@2026',
            'password_confirmation' => 'NewSecret@2026',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Ce lien de réinitialisation a expiré. Veuillez en demander un nouveau.',
            ]);

        // Mot de passe inchangé
        $this->assertFalse(Hash::check('NewSecret@2026', $user->fresh()->password));
    }

    #[Test]
    public function it_rejects_token_used_with_wrong_email(): void
    {
        $token = Str::random(64);

        $user = User::factory()->eleveur()->create([
            'is_verified'               => true,
            'password_reset_token'      => $token,
            'password_reset_expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'autre@macif.sn', // mauvais email
            'token'                 => $token,
            'password'              => 'NewSecret@2026',
            'password_confirmation' => 'NewSecret@2026',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_requires_all_fields_for_reset(): void
    {
        $response = $this->postJson('/api/auth/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    #[Test]
    public function it_requires_password_confirmation(): void
    {
        $token = Str::random(64);
        $user  = User::factory()->eleveur()->create([
            'is_verified'               => true,
            'password_reset_token'      => $token,
            'password_reset_expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'    => $user->email,
            'token'    => $token,
            'password' => 'NewSecret@2026',
            // password_confirmation manquant
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}