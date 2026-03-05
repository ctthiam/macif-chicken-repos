<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement confirmé</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #1B4332; color: white; padding: 24px; text-align: center; }
        .body { padding: 24px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .label { color: #666; font-size: 14px; }
        .value { font-weight: bold; color: #222; }
        .recap { background: #f0f7f4; border-radius: 6px; padding: 16px; margin: 20px 0; }
        .montant { font-size: 22px; color: #1B4332; font-weight: bold; }
        .commission { font-size: 13px; color: #888; margin-top: 6px; }
        .footer { background: #f9f9f9; padding: 16px; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🐔 MACIF CHICKEN</h1>
        <p style="margin:8px 0 0;">✅ Paiement confirmé</p>
    </div>

    <div class="body">
        <p>Bonjour <strong>{{ $commande->acheteur->name }}</strong>,</p>
        <p>Votre paiement pour la commande <strong>#{{ $commande->id }}</strong> a été confirmé avec succès.</p>

        <h3 style="color:#1B4332;">Récapitulatif du paiement</h3>

        <div class="info-row">
            <span class="label">Produit</span>
            <span class="value">{{ $commande->stock->titre ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Éleveur</span>
            <span class="value">{{ $commande->eleveur->name }}</span>
        </div>
        <div class="info-row">
            <span class="label">Quantité</span>
            <span class="value">{{ $commande->quantite }} unité(s)</span>
        </div>
        <div class="info-row">
            <span class="label">Mode de paiement</span>
            <span class="value">{{ strtoupper(str_replace('_', ' ', $commande->mode_paiement ?? 'N/A')) }}</span>
        </div>

        <div class="recap">
            <div class="label">Montant payé</div>
            <div class="montant">{{ number_format($commande->montant_total, 0, ',', ' ') }} FCFA</div>
            <div class="commission">dont {{ number_format($commande->commission_plateforme, 0, ',', ' ') }} FCFA de frais de service</div>
        </div>

        <p style="font-size:13px;color:#666;">
            Les fonds sont sécurisés en <strong>escrow</strong> jusqu'à confirmation de la livraison.<br>
            Date de paiement : <strong>{{ now()->format('d/m/Y à H:i') }}</strong>
        </p>
    </div>

    <div class="footer">
        MACIF CHICKEN — Marketplace avicole du Sénégal<br>
        Cet email a été envoyé automatiquement, merci de ne pas y répondre.
    </div>
</div>
</body>
</html>