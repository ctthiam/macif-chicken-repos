<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu Commande #{{ $commande->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 13px;
            color: #222;
            background: #fff;
            padding: 30px;
        }

        /* ── Header ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #e07b00;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }
        .brand { font-size: 26px; font-weight: bold; color: #e07b00; letter-spacing: 1px; }
        .brand span { color: #222; }
        .brand-sub { font-size: 11px; color: #777; margin-top: 3px; }
        .recu-title { text-align: right; }
        .recu-title h2 { font-size: 20px; color: #333; margin-bottom: 4px; }
        .recu-title .ref { font-size: 11px; color: #888; }

        /* ── Statut badge ── */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-paye    { background: #d4edda; color: #155724; }
        .badge-libere  { background: #cce5ff; color: #004085; }
        .badge-attente { background: #fff3cd; color: #856404; }
        .badge-rembourse { background: #f8d7da; color: #721c24; }

        /* ── Parties ── */
        .parties {
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 20px;
        }
        .partie {
            flex: 1;
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 14px;
        }
        .partie h3 {
            font-size: 11px;
            text-transform: uppercase;
            color: #e07b00;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            border-bottom: 1px solid #eee;
            padding-bottom: 6px;
        }
        .partie p { font-size: 12px; color: #444; line-height: 1.6; }
        .partie strong { color: #222; }

        /* ── Table articles ── */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #e07b00;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        thead th {
            background: #e07b00;
            color: #fff;
            padding: 8px 10px;
            font-size: 11px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        tbody td {
            padding: 9px 10px;
            font-size: 12px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #fafafa; }
        .text-right { text-align: right; }

        /* ── Totaux ── */
        .totaux {
            margin-left: auto;
            width: 280px;
            margin-bottom: 24px;
        }
        .totaux table { margin-bottom: 0; }
        .totaux td { border: none; padding: 5px 10px; }
        .totaux .total-row td {
            font-weight: bold;
            font-size: 14px;
            background: #e07b00;
            color: #fff;
            border-radius: 3px;
        }
        .totaux .commission-row td { color: #888; font-size: 11px; }

        /* ── Infos commande ── */
        .infos-grid {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }
        .info-block {
            flex: 1;
            padding: 10px 14px;
            border-left: 3px solid #e07b00;
            background: #fffaf5;
        }
        .info-block label { font-size: 10px; text-transform: uppercase; color: #aaa; display: block; }
        .info-block span  { font-size: 13px; font-weight: bold; color: #333; }

        /* ── Footer ── */
        .footer {
            border-top: 2px solid #eee;
            padding-top: 14px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer p { font-size: 10px; color: #bbb; }
        .footer .generated { font-size: 10px; color: #ccc; text-align: right; }
    </style>
</head>
<body>

    {{-- ── Header ── --}}
    <div class="header">
        <div>
            <div class="brand">MACIF<span> CHICKEN</span></div>
            <div class="brand-sub">Plateforme de vente de poulets au Sénégal</div>
        </div>
        <div class="recu-title">
            <h2>REÇU DE PAIEMENT</h2>
            <div class="ref">Référence : {{ $paiement->reference_transaction ?? 'N/A' }}</div>
            <div style="margin-top:6px">
                @php
                    $badgeClass = match($commande->statut_paiement) {
                        'paye'      => 'badge-paye',
                        'libere'    => 'badge-libere',
                        'rembourse' => 'badge-rembourse',
                        default     => 'badge-attente',
                    };
                    $badgeLabel = match($commande->statut_paiement) {
                        'paye'      => 'Payé',
                        'libere'    => 'Fonds libérés',
                        'rembourse' => 'Remboursé',
                        default     => 'En attente',
                    };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
            </div>
        </div>
    </div>

    {{-- ── Parties ── --}}
    <div class="parties">
        <div class="partie">
            <h3>Acheteur</h3>
            <p>
                <strong>{{ $commande->acheteur->name }}</strong><br>
                {{ $commande->acheteur->phone ?? '' }}<br>
                {{ $commande->adresse_livraison }}
            </p>
        </div>
        <div class="partie">
            <h3>Éleveur</h3>
            <p>
                <strong>{{ $commande->eleveur->name }}</strong><br>
                {{ $commande->eleveur->phone ?? '' }}<br>
                {{ $commande->eleveur->ville ?? '' }}
                @if($commande->eleveur->eleveurProfile)
                    <br>{{ $commande->eleveur->eleveurProfile->nom_poulailler }}
                @endif
            </p>
        </div>
        <div class="partie">
            <h3>Paiement</h3>
            <p>
                <strong>Mode :</strong>
                {{ match($commande->mode_paiement) {
                    'wave'         => 'Wave',
                    'orange_money' => 'Orange Money',
                    'free_money'   => 'Free Money',
                    default        => $commande->mode_paiement,
                } }}<br>
                <strong>Date :</strong> {{ $commande->created_at->format('d/m/Y') }}<br>
                @if($commande->escrow_libere_at)
                    <strong>Libéré :</strong> {{ $commande->escrow_libere_at->format('d/m/Y') }}
                @endif
            </p>
        </div>
    </div>

    {{-- ── Infos commande ── --}}
    <div class="section-title">Détail de la commande</div>
    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Mode de vente</th>
                <th class="text-right">Qté (poulets)</th>
                <th class="text-right">Poids moy. (kg)</th>
                <th class="text-right">Prix/kg (FCFA)</th>
                <th class="text-right">Sous-total (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $commande->stock->titre ?? 'Stock supprimé' }}</strong><br>
                    <span style="color:#888; font-size:11px">Commande #{{ $commande->id }}</span>
                </td>
                <td>
                    {{ match($commande->stock->mode_vente ?? '') {
                        'vivant'   => 'Vivant',
                        'abattu'   => 'Abattu',
                        'les_deux' => 'Vivant & Abattu',
                        default    => '—',
                    } }}
                </td>
                <td class="text-right">{{ $commande->quantite }}</td>
                <td class="text-right">{{ number_format($commande->stock->poids_moyen_kg ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($commande->stock->prix_par_kg ?? 0, 0, ',', ' ') }}</td>
                <td class="text-right"><strong>{{ number_format($commande->montant_total, 0, ',', ' ') }}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- ── Totaux ── --}}
    <div class="totaux">
        <table>
            <tr>
                <td>Sous-total</td>
                <td class="text-right">{{ number_format($commande->montant_total, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="commission-row">
                <td>Commission plateforme (7%)</td>
                <td class="text-right">- {{ number_format($commande->commission_plateforme, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td colspan="2" style="padding:4px 0;"></td>
            </tr>
            <tr class="total-row">
                <td>TOTAL PAYÉ</td>
                <td class="text-right">{{ number_format($commande->montant_total, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    {{-- ── Livraison ── --}}
    <div class="infos-grid">
        <div class="info-block">
            <label>Statut commande</label>
            <span>{{ ucfirst(str_replace('_', ' ', $commande->statut_commande)) }}</span>
        </div>
        <div class="info-block">
            <label>Poids total</label>
            <span>{{ number_format($commande->poids_total, 2) }} kg</span>
        </div>
        <div class="info-block">
            <label>Date souhaitée</label>
            <span>{{ $commande->date_livraison_souhaitee?->format('d/m/Y') ?? 'Non précisée' }}</span>
        </div>
        <div class="info-block">
            <label>Adresse livraison</label>
            <span style="font-size:11px">{{ $commande->adresse_livraison }}</span>
        </div>
    </div>

    {{-- ── Footer ── --}}
    <div class="footer">
        <p>MACIF CHICKEN — Plateforme de mise en relation éleveurs & acheteurs au Sénégal<br>
        Ce reçu est généré automatiquement et fait foi de paiement.</p>
        <div class="generated">
            Généré le {{ now()->format('d/m/Y à H:i') }}<br>
            macif-chicken.sn
        </div>
    </div>

</body>
</html>