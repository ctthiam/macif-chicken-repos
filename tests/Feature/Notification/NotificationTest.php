<?php

namespace Tests\Feature\Notification;

use App\Events\NouvelleCommandeEvent;
use App\Events\PaiementConfirmeEvent;
use App\Events\StatutCommandeEvent;
use App\Jobs\EnvoyerEmailCommandeJob;
use App\Jobs\EnvoyerEmailPaiementJob;
use App\Jobs\EnvoyerSmsJob;
use App\Mail\ConfirmationCommandeMail;
use App\Mail\ConfirmationPaiementMail;
use App\Models\Commande;
use App\Models\Notification;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature :
 *   NTF-01 — Notification in-app nouvelle commande (éleveur)
 *   NTF-02 — Notification in-app paiement reçu (éleveur)
 *   NTF-03 — Notification in-app statut commande (acheteur)
 *   NTF-04 — Email confirmation commande
 *   NTF-05 — Email confirmation paiement
 *   NTF-06 — SMS nouvelle commande (Job)
 *   NTF-07 — Alerte stock bientôt épuisé
 *   NTF-08 — Alerte abonnement expiré (déjà couvert Sprint 7)
 *   NTF-09 — Centre notifications (liste + marquer lu)
 *
 * Fichier : tests/Feature/Notification/NotificationTest.php
 * Lancer  : php artisan test --filter=NotificationTest
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────

    private function eleveur(): User
    {
        $u = User::factory()->eleveur()->create(['is_verified' => true, 'is_active' => true]);
        $u->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme Test',
            'description'    => 'Ferme de test.',
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

    private function commande(User $eleveur, User $acheteur, string $statut = 'confirmee', int $quantite = 5): Commande
    {
        $stock = Stock::factory()->create([
            'eleveur_id'          => $eleveur->id,
            'quantite_disponible' => 20,
        ]);
        return Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => $statut,
            'quantite'        => $quantite,
            'montant_total'   => 25000,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // NTF-01 — Notification in-app nouvelle commande
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sends_inapp_notification_to_eleveur_on_new_order(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        app(\App\Listeners\NouvelleCommandeListener::class)
            ->handle(new NouvelleCommandeEvent($commande));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $eleveur->id,
            'type'    => 'new_order',
        ]);
    }

    #[Test]
    public function it_dispatches_email_job_on_new_order(): void
    {
        Queue::fake();

        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        // Appel direct du listener (bypasse ShouldQueue)
        app(\App\Listeners\NouvelleCommandeListener::class)
            ->handle(new NouvelleCommandeEvent($commande));

        Queue::assertPushed(EnvoyerEmailCommandeJob::class);
    }

    #[Test]
    public function it_dispatches_sms_job_when_eleveur_has_phone(): void
    {
        Queue::fake();

        $eleveur = User::factory()->eleveur()->create([
            'is_verified' => true,
            'phone'       => '+221771234567',
        ]);
        $eleveur->eleveurProfile()->create([
            'nom_poulailler' => 'Ferme SMS',
            'description'    => 'Test.',
            'localisation'   => 'Dakar',
            'is_certified'   => false,
            'note_moyenne'   => 0.0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ]);
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        app(\App\Listeners\NouvelleCommandeListener::class)
            ->handle(new NouvelleCommandeEvent($commande));

        Queue::assertPushed(EnvoyerSmsJob::class);
    }

    // ══════════════════════════════════════════════════════════════
    // NTF-02 — Notification in-app paiement reçu
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sends_inapp_notification_to_eleveur_on_payment(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        app(\App\Listeners\PaiementConfirmeListener::class)
            ->handle(new PaiementConfirmeEvent($commande));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $eleveur->id,
            'type'    => 'payment',
        ]);
    }

    #[Test]
    public function it_dispatches_email_paiement_job_on_payment(): void
    {
        Queue::fake();

        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        app(\App\Listeners\PaiementConfirmeListener::class)
            ->handle(new PaiementConfirmeEvent($commande));

        Queue::assertPushed(EnvoyerEmailPaiementJob::class);
    }

    // ══════════════════════════════════════════════════════════════
    // NTF-03 — Notification in-app statut commande
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sends_inapp_notification_to_acheteur_on_statut_change(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        app(\App\Listeners\StatutCommandeListener::class)
            ->handle(new StatutCommandeEvent($commande, 'confirmee'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $acheteur->id,
            'type'    => 'delivery',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // NTF-04 — Email confirmation commande
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sends_confirmation_email_to_acheteur_and_eleveur(): void
    {
        Mail::fake();

        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        (new EnvoyerEmailCommandeJob($commande))->handle();

        Mail::assertSent(ConfirmationCommandeMail::class, 2); // acheteur + éleveur
    }

    #[Test]
    public function it_sends_email_to_correct_recipients(): void
    {
        Mail::fake();

        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        (new EnvoyerEmailCommandeJob($commande))->handle();

        Mail::assertSent(ConfirmationCommandeMail::class, function ($mail) use ($acheteur) {
            return $mail->hasTo($acheteur->email);
        });
        Mail::assertSent(ConfirmationCommandeMail::class, function ($mail) use ($eleveur) {
            return $mail->hasTo($eleveur->email);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // NTF-05 — Email confirmation paiement
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sends_paiement_email_to_acheteur(): void
    {
        Mail::fake();

        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $commande = $this->commande($eleveur, $acheteur);

        (new EnvoyerEmailPaiementJob($commande))->handle();

        Mail::assertSent(ConfirmationPaiementMail::class, function ($mail) use ($acheteur) {
            return $mail->hasTo($acheteur->email);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // NTF-06 — SMS offline (sans credentials)
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_skips_sms_api_call_when_no_twilio_credentials(): void
    {
        // Sans TWILIO_SID → le job log seulement, pas d'appel HTTP
        $job = new EnvoyerSmsJob('+221771234567', 'Test MACIF CHICKEN SMS.');
        $job->handle(); // Ne doit pas lever d'exception

        $this->assertTrue(true); // Job s'exécute sans erreur
    }

    // ══════════════════════════════════════════════════════════════
    // NTF-07 — Alerte stock bientôt épuisé
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_sends_alert_when_stock_below_10(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $stock    = Stock::factory()->create([
            'eleveur_id'          => $eleveur->id,
            'quantite_disponible' => 5, // < 10
        ]);
        $commande = Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'en_preparation',
        ]);

        app(\App\Listeners\StatutCommandeListener::class)
            ->handle(new StatutCommandeEvent($commande, 'confirmee'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $eleveur->id,
            'type'    => 'system',
        ]);
    }

    #[Test]
    public function it_does_not_alert_when_stock_above_10(): void
    {
        $eleveur  = $this->eleveur();
        $acheteur = $this->acheteur();
        $stock    = Stock::factory()->create([
            'eleveur_id'          => $eleveur->id,
            'quantite_disponible' => 50, // > 10
        ]);
        $commande = Commande::factory()->create([
            'eleveur_id'      => $eleveur->id,
            'acheteur_id'     => $acheteur->id,
            'stock_id'        => $stock->id,
            'statut_commande' => 'en_preparation',
        ]);

        app(\App\Listeners\StatutCommandeListener::class)
            ->handle(new StatutCommandeEvent($commande, 'confirmee'));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $eleveur->id,
            'type'    => 'system',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // NTF-09 — Centre notifications
    // ══════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_notifications_list(): void
    {
        $user = $this->acheteur();
        Notification::factory()->count(3)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [['id', 'titre', 'message', 'type', 'is_read']],
                'meta' => ['total', 'non_lues'],
            ]);

        $this->assertEquals(3, $response->json('meta.total'));
    }

    #[Test]
    public function it_marks_single_notification_as_read(): void
    {
        $user = $this->acheteur();
        $notif = Notification::factory()->create(['user_id' => $user->id, 'is_read' => false]);

        Sanctum::actingAs($user);

        $this->putJson("/api/notifications/{$notif->id}/lu")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('notifications', ['id' => $notif->id, 'is_read' => true]);
    }

    #[Test]
    public function it_marks_all_notifications_as_read(): void
    {
        $user = $this->acheteur();
        Notification::factory()->count(4)->create(['user_id' => $user->id, 'is_read' => false]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/notifications/tout-lire');

        $response->assertStatus(200)->assertJsonPath('updated', 4);
        $this->assertEquals(0, Notification::where('user_id', $user->id)->where('is_read', false)->count());
    }

    #[Test]
    public function it_filters_unread_notifications(): void
    {
        $user = $this->acheteur();
        Notification::factory()->count(2)->create(['user_id' => $user->id, 'is_read' => false]);
        Notification::factory()->count(3)->create(['user_id' => $user->id, 'is_read' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications?non_lues=1');

        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_returns_404_for_other_user_notification(): void
    {
        $user1 = $this->acheteur();
        $user2 = $this->acheteur();
        $notif = Notification::factory()->create(['user_id' => $user1->id]);

        Sanctum::actingAs($user2);

        $this->putJson("/api/notifications/{$notif->id}/lu")->assertStatus(404);
    }

    #[Test]
    public function it_requires_auth_for_notifications(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }
}