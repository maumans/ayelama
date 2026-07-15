<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $recu->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #15263F; }
        .header { text-align: center; border-bottom: 2px solid #B0863C; padding-bottom: 12px; margin-bottom: 24px; }
        .header h1 { font-size: 18px; margin: 0; color: #15263F; }
        .header p { font-size: 11px; color: #555; margin: 2px 0; }
        .titre { text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin: 24px 0; letter-spacing: 1px; }
        .numero { text-align: center; font-size: 12px; color: #B0863C; font-weight: bold; margin-bottom: 24px; }
        table.infos { width: 100%; margin-bottom: 24px; }
        table.infos td { padding: 4px 0; vertical-align: top; }
        table.infos td.label { width: 180px; color: #555; }
        .montant-box { border: 1px solid #B0863C; background: #F5EDD8; padding: 14px; text-align: center; margin: 24px 0; }
        .montant-box .chiffres { font-size: 20px; font-weight: bold; color: #15263F; }
        .montant-box .lettres { font-size: 12px; font-style: italic; margin-top: 6px; }
        .signature { margin-top: 60px; text-align: right; }
        .signature .ligne { display: inline-block; width: 220px; border-top: 1px solid #999; padding-top: 4px; text-align: center; font-size: 11px; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ \App\Models\Setting::get('office_nom', 'Étude Notariale Ayelama BAH') }}</h1>
        <p>Nongo, 3ᵉ étage, Immeuble VISTA BANK — Commune de Ratoma/Lambanyi — Conakry</p>
        <p>Tél : 622 49 69 44 / 664 20 96 07 — Email : ayelama.bah@notaire-guinee.com</p>
    </div>

    <div class="titre">Reçu de paiement</div>
    <div class="numero">N° {{ $recu->numero }}</div>

    <table class="infos">
        <tr>
            <td class="label">Date d'émission</td>
            <td>{{ $recu->date_emission->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Dossier</td>
            <td>{{ $dossier->reference }} — {{ $dossier->objet }}</td>
        </tr>
        <tr>
            <td class="label">Client</td>
            <td>{{ $dossier->clientPrincipalLabel() ?? '—' }}</td>
        </tr>
        @if($paiement->moyen_paiement)
        <tr>
            <td class="label">Moyen de paiement</td>
            <td>{{ ucfirst($paiement->moyen_paiement) }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Date du paiement</td>
            <td>{{ $paiement->date_paiement->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="montant-box">
        <div class="chiffres">{{ number_format((float) $paiement->montant, 0, ',', ' ') }} GNF</div>
        <div class="lettres">{{ $montantEnLettres }} FRANCS GUINÉENS</div>
    </div>

    <div class="signature">
        <div class="ligne">Le Notaire</div>
    </div>
</body>
</html>
