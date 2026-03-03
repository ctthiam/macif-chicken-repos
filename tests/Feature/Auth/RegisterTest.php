<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature : AUTH-01 — Inscription éleveur
 *                 AUTH-02 — Inscription acheteur
 *
 * Lancer : php artisan test --filter=RegisterTest
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected string $endpoint = '/api/auth/register';

    // ──────────────────────────────────────────────────────────────
    // AUTH-01 — ÉLEVEUR
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_registers_an_eleveur_successfully(): void
    {
        $payload = [
            'name'                  => 'Mamadou Diallo',
            'email'                 => 'mamadou@macif.sn',
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221771234567',
            'role'                  => 'eleveur',
            'nom_poulailler'        => 'Ferme Diallo',
            'description'           => 'Spécialiste poulets fermiers',
            'localisation'          => 'Thiès, Sénégal',
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Inscription réussie. Veuillez vérifier votre email pour activer votre compte.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id', 'name', 'email', 'phone', 'role',
                    'is_verified', 'is_active', 'created_at',
                    'eleveur_profile' => [
                        'nom_poulailler', 'description', 'localisation',
                        'is_certified', 'note_moyenne', 'nombre_avis',
                    ],
                ],
            ]);

        // Vérifier en base
        $this->assertDatabaseHas('users', [
            'email' => 'mamadou@macif.sn',
            'role'  => 'eleveur',
            'is_verified' => false,
        ]);

        $this->assertDatabaseHas('eleveur_profiles', [
            'nom_poulailler' => 'Ferme Diallo',
        ]);

        // Rôle Spatie assigné
        $user = User::where('email', 'mamadou@macif.sn')->first();
        $this->assertTrue($user->hasRole('eleveur'));
    }

    #[Test]
    public function it_requires_nom_poulailler_for_eleveur(): void
    {
        $payload = [
            'name'                  => 'Test Eleveur',
            'email'                 => 'test@macif.sn',
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221771234568',
            'role'                  => 'eleveur',
            // nom_poulailler manquant
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['nom_poulailler']);
    }

    // ──────────────────────────────────────────────────────────────
    // AUTH-02 — ACHETEUR
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_registers_an_acheteur_successfully(): void
    {
        $payload = [
            'name'                  => 'Fatou Restaurant',
            'email'                 => 'fatou@resto.sn',
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221771234569',
            'role'                  => 'acheteur',
            'type'                  => 'restaurant',
            'nom_etablissement'     => 'Chez Fatou',
            'ninea'                 => '12345678',
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'acheteur_profile' => ['type', 'nom_etablissement', 'ninea'],
                ],
            ]);

        $this->assertDatabaseHas('acheteur_profiles', [
            'type'              => 'restaurant',
            'nom_etablissement' => 'Chez Fatou',
        ]);

        $user = User::where('email', 'fatou@resto.sn')->first();
        $this->assertTrue($user->hasRole('acheteur'));
    }

    #[Test]
    public function it_requires_type_for_acheteur(): void
    {
        $payload = [
            'name'                  => 'Test Acheteur',
            'email'                 => 'acheteur@test.sn',
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221771234570',
            'role'                  => 'acheteur',
            // type manquant
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['type']);
    }

    // ──────────────────────────────────────────────────────────────
    // Validations communes
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_rejects_duplicate_email(): void
    {
        User::factory()->eleveur()->create(['email' => 'deja@macif.sn']);

        $payload = [
            'name'                  => 'Autre User',
            'email'                 => 'deja@macif.sn', // dupliqué
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221779999999',
            'role'                  => 'eleveur',
            'nom_poulailler'        => 'Ferme Test',
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_rejects_duplicate_phone(): void
    {
        User::factory()->eleveur()->create(['phone' => '+221771111111']);

        $payload = [
            'name'                  => 'Test',
            'email'                 => 'unique@macif.sn',
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221771111111', // dupliqué
            'role'                  => 'eleveur',
            'nom_poulailler'        => 'Ferme',
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    public function it_rejects_admin_role_on_register(): void
    {
        $payload = [
            'name'                  => 'Faux Admin',
            'email'                 => 'admin2@macif.sn',
            'password'              => 'Secret@2026',
            'password_confirmation' => 'Secret@2026',
            'phone'                 => '+221770000001',
            'role'                  => 'admin', // interdit
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    #[Test]
    public function it_returns_json_format_with_success_key(): void
    {
        $response = $this->postJson($this->endpoint, []);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors']);

        $this->assertFalse($response->json('success'));
    }
}