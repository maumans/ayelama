<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche de recueil — {{ $dossier->reference }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #15263F; }
        .header { text-align: center; border-bottom: 2px solid #B0863C; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; margin: 0; color: #15263F; }
        .header p { font-size: 11px; color: #555; margin: 2px 0; }
        .titre { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; margin: 20px 0; letter-spacing: 1px; }
        table.infos { width: 100%; margin-bottom: 20px; }
        table.infos td { padding: 3px 0; vertical-align: top; }
        table.infos td.label { width: 160px; color: #555; }
        .parties { margin-bottom: 20px; }
        .parties h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #B0863C; border-bottom: 1px solid #eee; padding-bottom: 4px; margin-bottom: 8px; }
        .parties table { width: 100%; border-collapse: collapse; }
        .parties td, .parties th { border-bottom: 1px solid #eee; padding: 4px 6px; text-align: left; font-size: 11px; }
        .groupe { margin-bottom: 16px; }
        .groupe h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #B0863C; border-bottom: 1px solid #eee; padding-bottom: 4px; margin-bottom: 8px; }
        .groupe table { width: 100%; }
        .groupe td { padding: 3px 0; font-size: 11px; vertical-align: top; }
        .groupe td.label { width: 220px; color: #555; }
        .accord { margin-top: 30px; border: 1px solid #B0863C; background: #F5EDD8; padding: 12px; font-size: 11px; }
        .signature { margin-top: 50px; }
        .signature .ligne { display: inline-block; width: 220px; border-top: 1px solid #999; padding-top: 4px; text-align: center; font-size: 11px; color: #555; }
        .signature .bloc { display: inline-block; width: 48%; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ \App\Models\Setting::get('office_nom', 'Étude Notariale Ayelama BAH') }}</h1>
        <p>Nongo, 3ᵉ étage, Immeuble VISTA BANK — Commune de Ratoma/Lambanyi — Conakry</p>
        <p>Tél : 622 49 69 44 / 664 20 96 07 — Email : ayelama.bah@notaire-guinee.com</p>
    </div>

    <div class="titre">Fiche de recueil — récapitulatif du questionnaire</div>

    <table class="infos">
        <tr>
            <td class="label">Référence dossier</td>
            <td>{{ $dossier->reference }}</td>
        </tr>
        <tr>
            <td class="label">Objet</td>
            <td>{{ $dossier->objet }}</td>
        </tr>
        <tr>
            <td class="label">Type d'acte</td>
            <td>{{ $dossier->typeActe?->label }}</td>
        </tr>
        <tr>
            <td class="label">Date d'édition</td>
            <td>{{ now()->format('d/m/Y') }}</td>
        </tr>
    </table>

    @if($dossier->parties->isNotEmpty())
    <div class="parties">
        <h2>Parties</h2>
        <table>
            <tr>
                <th>Rôle</th>
                <th>Nom</th>
                <th>Pièce d'identité</th>
                <th>Téléphone</th>
            </tr>
            @foreach($dossier->parties as $partie)
            <tr>
                <td>{{ $partie->role }}</td>
                <td>{{ $partie->nom }}</td>
                <td>{{ $partie->cni ?: '—' }}</td>
                <td>{{ $partie->telephone ?: '—' }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    @foreach($groupes as $prefixe => $champs)
    <div class="groupe">
        <h2>{{ $prefixe !== '' ? strtoupper($prefixe) : 'Informations générales' }}</h2>
        <table>
            @foreach($champs as $champ)
            <tr>
                <td class="label">{{ $champ['label'] }}</td>
                <td>{{ $champ['valeur'] }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endforeach

    <div class="accord">
        Je soussigné(e) certifie avoir pris connaissance des informations ci-dessus et les
        approuve pour la constitution du dossier référencé {{ $dossier->reference }}.
    </div>

    <div class="signature">
        <div class="bloc">
            <div class="ligne">Date</div>
        </div>
        <div class="bloc">
            <div class="ligne">Signature du client</div>
        </div>
    </div>
</body>
</html>
