<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation commande</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #2D6A4F; color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .body { padding: 24px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .label { color: #666; font-size: 14px; }
        .value { font-weight: bold; color: #222; }
        .total { background: #f0f7f4; border-radius: 6px; padding: 16px; margin: 20px 0; }
        .total .montant { font-size: 22px; color: #2D6A4F; font-weight: bold; }
        .footer { background: #f9f9f9; padding: 16px; text-align: center; font-size: 12px; color: #999; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; background: #d4edda; color: #155724; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🐔 MACIF CHICKEN</h1>
        @if($destinataire === 'eleveur')
            <p style="margin:8px 0 0;">Nouvelle commande reçue !</p>
        @else
            <p style="margin:8px 0 0;">Votre commande est confirmée !</p>
        @endif
    </div>

    <div class="body">
        @if($destinataire === 'acheteur')
            <p>Bonjour <strong>{{ $commande->acheteur->name }}</strong>,</p>
            <p>Votre commande a bien été enregistrée. L'éleveur va préparer votre commande.</p>
        @else
            <p>Bonjour <strong>{{ $commande->eleveur->name }}</strong>,</p>
            <p>Vous avez reçu une nouvelle commande de <strong>{{ $commande->acheteur->name }}</strong>.</p>
        @endif

        <h3 style="color:#2D6A4F;">Détails de la commande #{{ $commande->id }}</h3>

        <div class="info-row">
            <span class="label">Produit</span>
            <span class="value">{{ $commande->stock->titre ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Quantité</span>
            <span class="value">{{ $commande->quantite }} unité(s)</span>
        </div>
        <div class="info-row">
            <span class="label">Adresse de livraison</span>
            <span class="value">{{ $commande->adresse_livraison }}</span>
        </div>
        <div class="info-row">
            <span class="label">Mode de paiement</span>
            <span class="value">{{ strtoupper(str_replace('_', ' ', $commande->mode_paiement ?? 'N/A')) }}</span>
        </div>

        <div class="total">
            <div class="label">Montant total</div>
            <div class="montant">{{ number_format($commande->montant_total, 0, ',', ' ') }} FCFA</div>
        </div>

        <p style="font-size:13px;color:#666;">
            Référence commande : <strong>#{{ $commande->id }}</strong><br>
            Date : <strong>{{ $commande->created_at->format('d/m/Y à H:i') }}</strong>
        </p>
    </div>

    <div class="footer">
        MACIF CHICKEN — Marketplace avicole du Sénégal<br>
        Cet email a été envoyé automatiquement, merci de ne pas y répondre.
    </div>
</div>
</body>
</html>