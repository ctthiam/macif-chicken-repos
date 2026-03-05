<?php

namespace App\Mail;

use App\Models\Commande;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable : NTF-04 — Confirmation de commande.
 *
 * Fichier : app/Mail/ConfirmationCommandeMail.php
 *
 * Template : resources/views/emails/confirmation-commande.blade.php
 *
 * @param string $destinataire  'acheteur' | 'eleveur'
 */
class ConfirmationCommandeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Commande $commande,
        public readonly string   $destinataire = 'acheteur'
    ) {}

    public function envelope(): Envelope
    {
        $sujet = $this->destinataire === 'eleveur'
            ? "Nouvelle commande #{$this->commande->id} reçue"
            : "Confirmation de votre commande #{$this->commande->id}";

        return new Envelope(subject: $sujet);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confirmation-commande',
            with: [
                'commande'      => $this->commande,
                'destinataire'  => $this->destinataire,
            ]
        );
    }
}