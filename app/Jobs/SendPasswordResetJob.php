<?php

namespace App\Jobs;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Job : Envoie l'email de réinitialisation de mot de passe en queue.
 *
 * Fichier : app/Jobs/SendPasswordResetJob.php
 */
class SendPasswordResetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
    ) {}

    public function handle(): void
    {
        try {
            Mail::to($this->user->email)
                ->send(new ResetPasswordMail($this->user, $this->resetUrl));

            Log::info('Email reset password envoyé', ['user_id' => $this->user->id]);
        } catch (\Exception $e) {
            Log::error('Échec envoi reset password', [
                'user_id' => $this->user->id,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}