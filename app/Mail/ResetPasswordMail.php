<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable : Email de réinitialisation de mot de passe.
 * Lien avec token unique — expire après 1h.
 *
 * Fichier : app/Mail/ResetPasswordMail.php
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔐 MACIF CHICKEN — Réinitialisation de votre mot de passe',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.reset-password',
            with: [
                'userName' => $this->user->name,
                'resetUrl' => $this->resetUrl,
                'expiresInMinutes' => 60,
            ],
        );
    }
}