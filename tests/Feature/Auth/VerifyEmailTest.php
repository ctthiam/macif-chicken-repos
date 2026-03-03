<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendEmailVerificationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature : AUTH-03 — Vérification email
 *
 * Fichier : tests/Feature/Auth/VerifyEmailTest.php
 * Lancer  : php artisan test --filter=VerifyEmailTest
 */
class VerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // Tests sur verifyEmail()
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_verifies_email_with_valid_token(): void
    {
        $user = User::factory()->eleveur()->unverified()->create([
            'email_verification_token' => 'valid-token-abc123',
            'created_at'               => now()->subMinutes(30), // dans les 24h
        ]);

        $response = $this->getJson('/api/auth/verify-email/valid-token-abc123');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Adresse email vérifiée avec succès ! Vous pouvez maintenant vous connecter.',
            ])
            ->assertJsonPath('data.is_verified', true);

        // Vérifier en base
        $this->assertDatabaseHas('users', [
            'id'                       => $user->id,
            'is_verified'              => true,
            'email_verification_token' => null, // token supprimé
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function it_rejects_invalid_token(): void
    {
        $response = $this->getJson('/api/auth/verify-email/token-qui-nexiste-pas');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Lien de vérification invalide ou déjà utilisé.',
            ]);
    }

    #[Test]
    public function it_rejects_already_verified_account(): void
    {
        // Compte déjà vérifié (is_verified = true, token = null)
        $user = User::factory()->eleveur()->create([
            'is_verified'              => true,
            'email_verification_token' => 'some-token',
        ]);

        // On force un token pour simuler la double tentative
        $user->update(['email_verification_token' => 'some-token']);

        $response = $this->getJson('/api/auth/verify-email/some-token');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Votre adresse email est déjà vérifiée.',
            ]);
    }

    #[Test]
    public function it_rejects_expired_token(): void
    {
        // Compte créé il y a 25h (token expiré)
        $user = User::factory()->eleveur()->unverified()->create([
            'email_verification_token' => 'expired-token-xyz',
            'created_at'               => now()->subHours(25),
        ]);

        $response = $this->getJson('/api/auth/verify-email/expired-token-xyz');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Ce lien de vérification a expiré. Veuillez en demander un nouveau.',
            ]);

        // Le compte ne doit pas être vérifié
        $this->assertDatabaseHas('users', [
            'id'          => $user->id,
            'is_verified' => false,
        ]);
    }

    #[Test]
    public function it_token_cannot_be_reused_after_verification(): void
    {
        $user = User::factory()->eleveur()->unverified()->create([
            'email_verification_token' => 'use-once-token',
            'created_at'               => now()->subMinutes(10),
        ]);

        // Première vérification — succès
        $this->getJson('/api/auth/verify-email/use-once-token')
            ->assertStatus(200);

        // Deuxième tentative avec le même token — doit échouer
        $this->getJson('/api/auth/verify-email/use-once-token')
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ──────────────────────────────────────────────────────────────
    // Tests sur le dispatch du job lors de l'inscription
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_dispatches_verification_email_job_on_register(): void
    {
        // Intercepter la queue pour vérifier que le job est bien dispatché
        Queue::fake();

        $payload = [
            'name'                  => 'Test Eleveur',
            'email'                 => 'test@macif.sn',
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221771234567',
            'role'                  => 'eleveur',
            'nom_poulailler'        => 'Ferme Test',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertStatus(201);

        // Le job doit être dans la queue
        Queue::assertPushed(SendEmailVerificationJob::class, function ($job) {
            return $job->user->email === 'test@macif.sn';
        });
    }

    #[Test]
    public function it_creates_user_with_unverified_status_on_register(): void
    {
        Queue::fake(); // Bloquer l'envoi réel d'email

        $payload = [
            'name'                  => 'Nouveau User',
            'email'                 => 'nouveau@macif.sn',
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221771234568',
            'role'                  => 'acheteur',
            'type'                  => 'particulier',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_verified', false);

        // Token de vérification bien généré en base
        $user = User::where('email', 'nouveau@macif.sn')->first();
        $this->assertNotNull($user->email_verification_token);
        $this->assertEquals(64, strlen($user->email_verification_token));
    }
}