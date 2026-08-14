<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $recu->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; line-height: 1.5; }
        
        /* En-tête */
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 20px; font-weight: bold; color: #1A3A6B; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .header .subtitle { font-size: 14px; font-weight: bold; color: #1a1a1a; margin-top: 4px; margin-bottom: 10px; }
        .header p { font-size: 11px; color: #666; margin: 2px 0; }
        
        /* Bloc Titre et N° */
        .title-container { width: 100%; margin-bottom: 30px; border-bottom: 2px solid #1A3A6B; padding-bottom: 10px; }
        .title-box { font-size: 18px; font-weight: bold; color: #1A3A6B; text-align: left; float: left; width: 60%; }
        .date-box { text-align: right; float: right; width: 35%; font-size: 12px; font-style: italic; color: #666; margin-top: 4px; }
        .clear { clear: both; }

        /* Infos Client et Dossier */
        .info-section { margin-bottom: 30px; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 6px 0; vertical-align: top; }
        .info-table td.label { width: 140px; font-weight: bold; color: #1A3A6B; }
        .info-table td.value { color: #1a1a1a; }
        
        /* Détail du paiement (style tableau facture) */
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.details th { background-color: #f8fafc; border: 1px solid #1A3A6B; border-bottom: 2px solid #1A3A6B; padding: 10px; text-align: left; font-size: 12px; color: #1A3A6B; font-weight: bold; text-transform: uppercase; }
        table.details td { border: 1px solid #cbd5e1; padding: 10px; font-size: 13px; }
        
        /* Total */
        .total-container { width: 100%; margin-top: 20px; margin-bottom: 40px; }
        .total-box { border: 2px solid #1A3A6B; width: 100%; text-align: center; padding: 15px; background-color: #f8fafc; border-radius: 4px; }
        .total-box .chiffres { font-size: 22px; font-weight: bold; color: #1A3A6B; }
        .total-box .lettres { font-size: 13px; font-style: italic; color: #1a1a1a; margin-top: 5px; font-weight: bold; }
        
        /* Signature */
        .footer-signature { text-align: right; margin-top: 50px; padding-right: 20px; }
        .footer-signature .titre { font-weight: bold; color: #1A3A6B; font-size: 14px; margin-bottom: 60px; }
        .footer-signature .nom { font-weight: bold; font-size: 14px; }
        .footer-signature .qualite { font-size: 12px; color: #666; }
    </style>
</head>
<body>
    @php
        $logoPath = \App\Models\Setting::get('logo_path');
        $imgSrc = null;
        if ($logoPath) {
            $absPath = storage_path('app/public/' . $logoPath);
            if (file_exists($absPath) && !str_ends_with(strtolower($absPath), '.svg')) {
                $imgSrc = $absPath;
            }
        }
    @endphp

    <div class="header">
        @if($imgSrc)
            <img src="{{ $imgSrc }}" alt="Logo" style="max-height: 80px; margin-bottom: 10px;">
        @endif
        <h1>{{ \App\Models\Setting::get('office_nom', 'Étude Notariale Ayelama BAH') }}</h1>
        <div class="subtitle">Notaire — Cabinet Notarial de Conakry</div>
        <p>📍 {{ \App\Models\Setting::get('office_adresse', 'Conakry, République de Guinée') }}</p>
        <p>📞 {{ \App\Models\Setting::get('office_telephone', '+224 622 49 69 44') }}   ✉ {{ \App\Models\Setting::get('office_email', 'contact@notaire-ayelama-bah.gn') }}</p>
    </div>

    <div class="title-container">
        <div class="title-box">
            REÇU DE PAIEMENT N° {{ $recu->numero }}
        </div>
        <div class="date-box">
            Conakry, le {{ $recu->date_emission ? $recu->date_emission->format('d/m/Y') : date('d/m/Y') }}
        </div>
        <div class="clear"></div>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td class="label">Reçu de :</td>
                <td class="value"><strong>{{ $dossier->clientPrincipalLabel() ?? '—' }}</strong></td>
            </tr>
            <tr>
                <td class="label">Pour le dossier :</td>
                <td class="value">Réf. {{ $dossier->reference }} — {{ $dossier->objet }}</td>
            </tr>
            @if($paiement->notes)
            <tr>
                <td class="label">Notes / Motif :</td>
                <td class="value">{{ $paiement->notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    <table class="details">
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Mode de paiement</th>
                <th>Date du paiement</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Paiement sur note de frais (Dossier {{ $dossier->reference }})</td>
                <td>{{ $paiement->moyen_paiement ? ucfirst($paiement->moyen_paiement) : 'Non précisé' }}</td>
                <td>{{ $paiement->date_paiement ? $paiement->date_paiement->format('d/m/Y') : date('d/m/Y') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-container">
        <div class="total-box">
            <div class="chiffres">{{ number_format((float) $paiement->montant, 0, ',', ' ') }} GNF</div>
            <div class="lettres">Arrêté en toutes lettres : <br> {{ mb_convert_case(mb_strtolower($montantEnLettres, 'UTF-8'), MB_CASE_TITLE, 'UTF-8') }} Francs Guinéens</div>
        </div>
    </div>

    <p style="text-align: center; font-style: italic; color: #666; font-size: 12px; margin-top: 40px;">
        Pour valoir et servir ce que de droit.
    </p>

    <div class="footer-signature">
        <div class="titre">LE NOTAIRE</div>
        <div class="nom">Maître Ayelama Bah</div>
        <div class="qualite">Notaire — Conakry, Guinée</div>
    </div>
</body>
</html>
