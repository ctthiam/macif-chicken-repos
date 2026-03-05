<?php

namespace App\Mail;

use App\Models\Commande;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable : NTF-05 — Confirmation paiement avec récapitulatif.
 *
 * Fichier : app/Mail/ConfirmationPaiementMail.php
 *
 * Template : resources/views/emails/confirmation-paiement.blade.php
 */
class ConfirmationPaiementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Commande $commande
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Paiement confirmé — Commande #{$this->commande->id}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confirmation-paiement',
            with: ['commande' => $this->commande]
        );
    }
}