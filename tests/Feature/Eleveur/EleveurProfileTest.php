<?php

namespace Tests\Feature\Eleveur;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   PRO-01 — Récupérer profil éleveur (GET /api/eleveur/profile)
 *   PRO-02 — Upload photos poulailler (inclus dans PUT)
 *   PRO-03 — Modifier profil éleveur (PUT /api/eleveur/profile)
 *
 * Fichier : tests/Feature/Eleveur/EleveurProfileTest.php
 * Lancer  : php artisan test --filter=EleveurProfileTest
 */
class EleveurProfileTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper : crée un éleveur authentifié avec profil ────────

    private function actingAsEleveur(): User
    {
        $user = User::factory()->eleveur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);

        // Créer le profil via la relation (pas besoin de EleveurProfile::factory())
        $user->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test Diallo',
            'description'    => 'Une belle ferme de test',
            'localisation'   => 'Route de Thiès, km 5',
            'latitude'       => 14.6928,
            'longitude'      => -17.4467,
            'is_certified'   => false,
            'note_moyenne'   => 0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ]);

        Sanctum::actingAs($user);

        return $user->load('eleveurProfile');
    }

    // ══════════════════════════════════════════════════════════════
    // PRO-01 — GET /api/eleveur/profile
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_eleveur_profile(): void
    {
        $user = $this->actingAsEleveur();

        $response = $this->getJson('/api/eleveur/profile');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'email', 'phone', 'role',
                    'eleveur_profile' => [
                        'id', 'nom_poulailler', 'description',
                        'localisation', 'latitude', 'longitude',
                        'is_certified', 'note_moyenne', 'nombre_avis', 'photos',
                    ],
                ],
            ]);

        $this->assertEquals($user->id, $response->json('data.id'));
    }

    #[Test]
    public function it_returns_401_when_not_authenticated(): void
    {
        $this->getJson('/api/eleveur/profile')
            ->assertStatus(401);
    }

    #[Test]
    public function it_returns_403_when_acheteur_tries_to_access_eleveur_profile(): void
    {
        $acheteur = User::factory()->acheteur()->create([
            'is_verified' => true,
            'is_active'   => true,
        ]);
        Sanctum::actingAs($acheteur);

        $this->getJson('/api/eleveur/profile')
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    // PRO-03 — PUT /api/eleveur/profile
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_updates_nom_poulailler(): void
    {
        $user = $this->actingAsEleveur();

        $response = $this->putJson('/api/eleveur/profile', [
            'nom_poulailler' => 'Ferme Bio Diallo',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.eleveur_profile.nom_poulailler', 'Ferme Bio Diallo');

        $this->assertDatabaseHas('eleveur_profiles', [
            'user_id'        => $user->id,
            'nom_poulailler' => 'Ferme Bio Diallo',
        ]);
    }

    #[Test]
    public function it_updates_description_and_localisation(): void
    {
        $user = $this->actingAsEleveur();

        $response = $this->putJson('/api/eleveur/profile', [
            'description'  => 'Spécialiste poulets fermiers depuis 2010',
            'localisation' => 'Route de Thiès, km 15',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.eleveur_profile.description', 'Spécialiste poulets fermiers depuis 2010')
            ->assertJsonPath('data.eleveur_profile.localisation', 'Route de Thiès, km 15');
    }

    #[Test]
    public function it_updates_gps_coordinates(): void
    {
        $user = $this->actingAsEleveur();

        $response = $this->putJson('/api/eleveur/profile', [
            'latitude'  => 14.6928,
            'longitude' => -17.4467,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('eleveur_profiles', [
            'user_id'   => $user->id,
            'latitude'  => 14.6928,
            'longitude' => -17.4467,
        ]);
    }

    #[Test]
    public function it_rejects_invalid_gps_coordinates(): void
    {
        $user = $this->actingAsEleveur();

        $response = $this->putJson('/api/eleveur/profile', [
            'latitude'  => 999,   // invalide : > 90
            'longitude' => -200,  // invalide : < -180
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    #[Test]
    public function it_updates_ville_and_adresse_on_user(): void
    {
        $user = $this->actingAsEleveur();

        $response = $this->putJson('/api/eleveur/profile', [
            'ville'   => 'Thiès',
            'adresse' => 'Quartier Escale',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'      => $user->id,
            'ville'   => 'Thiès',
            'adresse' => 'Quartier Escale',
        ]);
    }

    #[Test]
    public function it_does_nothing_when_no_fields_sent(): void
    {
        $user    = $this->actingAsEleveur();
        $profile = $user->eleveurProfile;

        $response = $this->putJson('/api/eleveur/profile', []);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.eleveur_profile.nom_poulailler', $profile->nom_poulailler);
    }

    // ══════════════════════════════════════════════════════════════
    // PRO-02 — Upload photos poulailler
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_uploads_photos_poulailler(): void
    {
        Storage::fake('public');
        $user = $this->actingAsEleveur();

        $photo1 = UploadedFile::fake()->image('poulailler1.jpg', 800, 600);
        $photo2 = UploadedFile::fake()->image('poulailler2.jpg', 800, 600);

        $response = $this->putJson('/api/eleveur/profile', [
            'photos' => [$photo1, $photo2],
        ]);

        $response->assertStatus(200);

        $photos = $response->json('data.eleveur_profile.photos');
        $this->assertIsArray($photos);
        $this->assertCount(2, $photos);
    }

    #[Test]
    public function it_adds_photos_to_existing_ones(): void
    {
        Storage::fake('public');
        $user    = $this->actingAsEleveur();
        $profile = $user->eleveurProfile;

        // Simuler 2 photos existantes
        $profile->update(['photos' => ['http://r2.example.com/photo1.jpg', 'http://r2.example.com/photo2.jpg']]);

        $newPhoto = UploadedFile::fake()->image('new.jpg', 400, 400);

        $response = $this->putJson('/api/eleveur/profile', [
            'photos' => [$newPhoto],
        ]);

        $response->assertStatus(200);

        $photos = $response->json('data.eleveur_profile.photos');
        // 2 existantes + 1 nouvelle = 3
        $this->assertCount(3, $photos);
    }

    #[Test]
    public function it_limits_photos_to_5_max(): void
    {
        Storage::fake('public');
        $user    = $this->actingAsEleveur();
        $profile = $user->eleveurProfile;

        // 4 photos existantes
        $profile->update(['photos' => [
            'http://r2.example.com/p1.jpg',
            'http://r2.example.com/p2.jpg',
            'http://r2.example.com/p3.jpg',
            'http://r2.example.com/p4.jpg',
        ]]);

        // Uploader 3 nouvelles → total serait 7 → tronqué à 5
        $photos = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ];

        $response = $this->putJson('/api/eleveur/profile', [
            'photos' => $photos,
        ]);

        $response->assertStatus(200);

        $resultPhotos = $response->json('data.eleveur_profile.photos');
        $this->assertLessThanOrEqual(5, count($resultPhotos));
    }

    #[Test]
    public function it_rejects_more_than_5_photos_in_one_upload(): void
    {
        $user = $this->actingAsEleveur();

        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = UploadedFile::fake()->image("photo{$i}.jpg");
        }

        $response = $this->putJson('/api/eleveur/profile', [
            'photos' => $photos,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    #[Test]
    public function it_rejects_non_image_in_photos(): void
    {
        $user = $this->actingAsEleveur();

        $response = $this->putJson('/api/eleveur/profile', [
            'photos' => [UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf')],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0']);
    }
}