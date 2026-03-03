<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation mot de passe — MACIF CHICKEN</title>
    <style>
        body { font-family: Arial, sans-serif; background: #FAFAFA; margin: 0; padding: 0; color: #1A1A1A; }
        .container { max-width: 560px; margin: 40px auto; background: #FFF; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1B5E20; padding: 32px 40px; text-align: center; }
        .header h1 { color: #FFF; font-size: 22px; font-weight: 800; margin: 0; }
        .header p { color: rgba(255,255,255,0.8); font-size: 13px; margin: 6px 0 0; }
        .body { padding: 36px 40px; }
        .body p { font-size: 15px; line-height: 1.6; color: #616161; margin: 0 0 16px; }
        .body p strong { color: #1A1A1A; }
        .btn-container { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; background: #FF6F00; color: #FFF !important; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 15px; font-weight: 700; }
        .expire-note { background: #FFF3E0; border-left: 3px solid #FF6F00; padding: 12px 16px; border-radius: 0 8px 8px 0; font-size: 13px; color: #616161; margin: 20px 0; }
        .warning { background: #FFEBEE; border-left: 3px solid #C62828; padding: 12px 16px; border-radius: 0 8px 8px 0; font-size: 13px; color: #616161; margin: 20px 0; }
        .url-fallback { background: #F5F5F5; border-radius: 8px; padding: 14px 16px; font-size: 12px; color: #9E9E9E; word-break: break-all; margin-top: 16px; }
        .footer { background: #F5F5F5; padding: 20px 40px; text-align: center; font-size: 12px; color: #9E9E9E; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐔 MACIF CHICKEN</h1>
            <p>Réinitialisation de mot de passe</p>
        </div>

        <div class="body">
            <p>Bonjour <strong>{{ $userName }}</strong>,</p>

            <p>
                Vous avez demandé la réinitialisation de votre mot de passe.
                Cliquez sur le bouton ci-dessous pour en définir un nouveau.
            </p>

            <div class="expire-note">
                ⏱ Ce lien expire dans <strong>{{ $expiresInMinutes }} minutes</strong>.
            </div>

            <div class="btn-container">
                <a href="{{ $resetUrl }}" class="btn">🔐 Réinitialiser mon mot de passe</a>
            </div>

            <p>Si le bouton ne fonctionne pas, copiez ce lien :</p>
            <div class="url-fallback">{{ $resetUrl }}</div>

            <div class="warning" style="margin-top: 24px;">
                ⚠️ Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.
                Votre mot de passe actuel reste inchangé.
            </div>
        </div>

        <div class="footer">
            © {{ date('Y') }} MACIF CHICKEN — Sénégal<br>
            Cet email a été envoyé automatiquement, merci de ne pas y répondre.
        </div>
    </div>
</body>
</html>