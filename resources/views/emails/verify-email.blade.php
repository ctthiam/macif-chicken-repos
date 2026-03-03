<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification email — MACIF CHICKEN</title>
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            background-color: #FAFAFA;
            margin: 0;
            padding: 0;
            color: #1A1A1A;
        }
        .container {
            max-width: 560px;
            margin: 40px auto;
            background: #FFFFFF;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header {
            background-color: #1B5E20;
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            color: #FFFFFF;
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .header p {
            color: rgba(255,255,255,0.8);
            font-size: 13px;
            margin: 6px 0 0;
        }
        .body {
            padding: 36px 40px;
        }
        .body p {
            font-size: 15px;
            line-height: 1.6;
            color: #616161;
            margin: 0 0 16px;
        }
        .body p strong {
            color: #1A1A1A;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #FF6F00;
            color: #FFFFFF !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
        }
        .url-fallback {
            background: #F5F5F5;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 12px;
            color: #9E9E9E;
            word-break: break-all;
            margin-top: 24px;
        }
        .footer {
            background: #F5F5F5;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #9E9E9E;
        }
        .expire-note {
            background: #FFF3E0;
            border-left: 3px solid #FF6F00;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            font-size: 13px;
            color: #616161;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">

        {{-- Header --}}
        <div class="header">
            <h1>🐔 MACIF CHICKEN</h1>
            <p>Plateforme d'achat de volaille au Sénégal</p>
        </div>

        {{-- Body --}}
        <div class="body">
            <p>Bonjour <strong>{{ $userName }}</strong>,</p>

            <p>
                Merci de vous être inscrit sur <strong>MACIF CHICKEN</strong> !
                Pour activer votre compte et commencer à utiliser la plateforme,
                veuillez vérifier votre adresse email en cliquant sur le bouton ci-dessous.
            </p>

            <div class="expire-note">
                ⏱ Ce lien expire dans <strong>{{ $expiresInHours }} heures</strong>.
                Après ce délai, vous devrez relancer une vérification.
            </div>

            <div class="btn-container">
                <a href="{{ $verificationUrl }}" class="btn">
                    ✅ Vérifier mon adresse email
                </a>
            </div>

            <p>
                Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :
            </p>
            <div class="url-fallback">
                {{ $verificationUrl }}
            </div>

            <p style="margin-top: 24px;">
                Si vous n'avez pas créé de compte sur MACIF CHICKEN,
                ignorez simplement cet email.
            </p>
        </div>

        {{-- Footer --}}
        <div class="footer">
            © {{ date('Y') }} MACIF CHICKEN — Sénégal<br>
            Cet email a été envoyé automatiquement, merci de ne pas y répondre.
        </div>

    </div>
</body>
</html>