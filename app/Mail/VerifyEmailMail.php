<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable : Email de vérification envoyé après inscription.
 * Contient un lien avec token unique — expire après 24h.
 *
 * Fichier : app/Mail/VerifyEmailMail.php
 */
class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param User   $user           L'utilisateur qui vient de s'inscrire
     * @param string $verificationUrl L'URL complète avec le token
     */
    public function __construct(
        public readonly User $user,
        public readonly string $verificationUrl,
    ) {}

    /**
     * Sujet de l'email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🐔 MACIF CHICKEN — Vérifiez votre adresse email',
        );
    }

    /**
     * Vue Blade utilisée pour le corps de l'email.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.verify-email',
            with: [
                'userName'        => $this->user->name,
                'verificationUrl' => $this->verificationUrl,
                'expiresInHours'  => 24,
            ],
        );
    }
}