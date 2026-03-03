<?php

namespace App\Jobs;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Job : Envoie l'email de vérification après inscription.
 * Est mis en queue (Redis) pour ne pas bloquer la réponse API.
 *
 * Dispatché depuis : AuthController::register()
 * Fichier : app/Jobs/SendEmailVerificationJob.php
 *
 * Usage :
 *   SendEmailVerificationJob::dispatch($user);
 */
class SendEmailVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives en cas d'échec (ex: Mailgun indisponible).
     */
    public int $tries = 3;

    /**
     * Délai entre les tentatives (en secondes).
     */
    public int $backoff = 60;

    public function __construct(
        public readonly User $user
    ) {}

    /**
     * Construit l'URL de vérification et envoie le Mailable.
     * L'URL pointe vers le frontend Angular qui appellera l'API.
     *
     * Format URL : {FRONTEND_URL}/auth/verify-email?token={token}
     * L'Angular appellera ensuite : GET /api/auth/verify-email/{token}
     */
    public function handle(): void
    {
        // Construire l'URL de vérification pointant vers le frontend Angular
        $verificationUrl = rtrim(config('app.frontend_url'), '/')
            . '/auth/verify-email?token='
            . $this->user->email_verification_token;

        try {
            Mail::to($this->user->email)
                ->send(new VerifyEmailMail($this->user, $verificationUrl));

            Log::info('Email de vérification envoyé', [
                'user_id' => $this->user->id,
                'email'   => $this->user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Échec envoi email de vérification', [
                'user_id' => $this->user->id,
                'email'   => $this->user->email,
                'error'   => $e->getMessage(),
            ]);

            // Relancer l'exception pour que la queue retente
            throw $e;
        }
    }
}