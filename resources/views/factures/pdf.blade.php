{{--
    Note de frais / facture, en PDF.

    ## La forme suivie

    Calquée sur **`Facture_MAB_SARLU_v2 (1).docx`**, l'exemplaire fourni par l'étude — relevé
    paragraphe par paragraphe, en-tête compris : papier à lettre (logo, « Maître Ayelama Bah »,
    qualité, coordonnées), titre FACTURE et numéro « … / MAB / 26 », date, n° de compte, objet suivi
    de sa parenthèse récapitulative, tableau des prestations, les deux lignes à montant variable
    « Timbres fiscaux » et « Rôles », le total, l'arrêté en toutes lettres, la phrase de courtoisie
    et le bloc de signature.

    La palette du document est reprise à l'identique : `#1A3A6B` (bleu de l'étude), `#1A1A1A`
    (texte), `#666666` (mentions secondaires), `#F5F5F5` (trame du tableau) — ce sont exactement les
    valeurs relevées dans le `.docx`, et celles que `recus/pdf.blade.php` emploie déjà.

    ⚠️ **Les pictogrammes du papier à lettre Word (📍 📞 ✉) ne sont pas repris** : dompdf n'embarque
    que DejaVu Sans, qui ne les contient pas — ils sortiraient en carrés vides. Les libellés les
    remplacent. (Le PDF du reçu, lui, les emploie encore : même défaut, à traiter séparément.)

    ## Pourquoi un PDF, et pas le .docx

    La route s'appelait `telechargerPdf` mais rendait le gabarit Word dans un fichier temporaire et
    renvoyait un `.docx`. L'étude attendait un PDF, et recevait par intermittence un
    `telecharger.htm` — la page HTML d'erreur enregistrée par l'attribut `download` du lien.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $facture->note_numero }}</title>
    <style>
        @page { margin: 18mm 16mm 20mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1A1A1A; line-height: 1.45; }

        /* Papier à lettre — header2.xml du document de l'étude */
        .entete { text-align: center; margin-bottom: 22px; }
        .entete .nom { font-size: 19px; font-weight: bold; color: #1A3A6B; margin: 0; }
        .entete .qualite { font-size: 12px; font-weight: bold; margin-top: 3px; }
        .entete .coordonnees { font-size: 10px; color: #666666; margin-top: 5px; }
        .entete .filet { border-bottom: 2px solid #1A3A6B; margin-top: 10px; }

        /* Titre et numéro */
        .titre { font-size: 22px; font-weight: bold; color: #1A3A6B; letter-spacing: 2px; margin-bottom: 2px; }
        .numero { font-size: 14px; font-weight: bold; color: #1A3A6B; margin-bottom: 18px; }

        table.champs { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.champs td { padding: 3px 0; vertical-align: top; font-size: 12px; }
        table.champs td.etiquette { width: 96px; font-weight: bold; color: #1A3A6B; }

        .rappel { font-size: 10.5px; color: #666666; font-style: italic; margin: 6px 0 16px; text-align: justify; }

        table.prestations { width: 100%; border-collapse: collapse; }
        table.prestations th {
            background-color: #F5F5F5; border: 1px solid #1A3A6B; border-bottom-width: 2px;
            padding: 7px 8px; text-align: left; font-size: 10.5px; color: #1A3A6B;
            font-weight: bold; text-transform: uppercase;
        }
        table.prestations td { border: 1px solid #C9D2DE; padding: 7px 8px; font-size: 11.5px; vertical-align: top; }
        table.prestations th.rang, table.prestations td.rang { width: 30px; text-align: center; }
        table.prestations th.qte, table.prestations td.qte { width: 40px; text-align: center; }
        table.prestations th.montant, table.prestations td.montant { width: 122px; text-align: right; white-space: nowrap; }
        .precision { display: block; font-size: 9.5px; color: #666666; font-style: italic; margin-top: 2px; }
        tr.variable td { color: #666666; }
        tr.total td {
            border-top: 2px solid #1A3A6B; background-color: #F5F5F5;
            font-weight: bold; color: #1A3A6B; font-size: 13px;
        }

        .lettres { margin-top: 12px; }
        .lettres .etiquette { font-weight: bold; color: #1A3A6B; }
        .lettres .valeur { font-weight: bold; font-style: italic; }

        .courtoisie { margin-top: 24px; margin-bottom: 28px; text-align: justify; }

        .signature { text-align: right; padding-right: 6px; }
        .signature .fonction { font-weight: bold; color: #1A3A6B; font-size: 13px; margin-bottom: 52px; }
        .signature .nom { font-weight: bold; font-size: 13px; }
        .signature .lieu { font-size: 11px; color: #666666; }
    </style>
</head>
<body>
    @php
        // dompdf ne rasterise pas un SVG : on l'écarte plutôt que de produire un bloc vide.
        $logoPath = \App\Models\Setting::get('logo_path');
        $logo = null;
        if ($logoPath) {
            $absolu = storage_path('app/public/' . $logoPath);
            if (file_exists($absolu) && !str_ends_with(strtolower($absolu), '.svg')) {
                $logo = $absolu;
            }
        }

        $fmt = fn ($montant) => number_format((float) $montant, 0, ',', ' ');
        $lignes = $facture->lignes;

        /*
         * Les désignations portent leur précision entre parenthèses, en base comme dans le
         * document de l'étude : « Insertion JAL (Journal Annonces Légales) ». Le formulaire
         * l'affiche sur une seconde ligne, en plus petit — on la sépare donc ici.
         *
         * C'est la même convention que `FactureGeneratorService::detailPrestations()`, qui coupe
         * déjà sur « ( » pour composer le rappel sous l'objet.
         */
        $decouper = function (string $designation) {
            $position = mb_strpos($designation, ' (');

            return $position === false
                ? [$designation, null]
                : [mb_substr($designation, 0, $position), mb_substr($designation, $position + 1)];
        };
    @endphp

    <div class="entete">
        @if($logo)
            <img src="{{ $logo }}" alt="" style="max-height: 66px; margin-bottom: 6px;">
        @endif
        <div class="nom">{{ \App\Models\Setting::get('office_nom', 'Maître Ayelama Bah') }}</div>
        <div class="qualite">Notaire — Cabinet Notarial de Conakry</div>
        <div class="coordonnees">
            {{ \App\Models\Setting::get('office_adresse', 'Conakry, République de Guinée') }}
            &nbsp;·&nbsp; Tél. {{ \App\Models\Setting::get('office_telephone', '+224 622 496 944') }}
            &nbsp;·&nbsp; {{ \App\Models\Setting::get('office_email', 'contact@notaire-ayelama-bah.gn') }}
        </div>
        <div class="filet"></div>
    </div>

    <div class="titre">FACTURE</div>
    <div class="numero">N° {{ $facture->note_numero }}</div>

    <table class="champs">
        <tr>
            <td class="etiquette">Date</td>
            <td>{{ $facture->note_date ? 'Le ' . $facture->note_date->translatedFormat('j F Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="etiquette">N° Compte</td>
            <td>{{ $facture->compte_numero ?: '—' }}</td>
        </tr>
        <tr>
            <td class="etiquette">Objet</td>
            <td>{{ $facture->objet }}</td>
        </tr>
    </table>

    @if(filled($detail))
        <div class="rappel">{{ $detail }}</div>
    @endif

    <table class="prestations">
        <thead>
            <tr>
                <th class="rang">N°</th>
                <th>Désignation des prestations</th>
                <th class="qte">Qté</th>
                <th class="montant">Montant (GNF)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lignes as $index => $ligne)
                @php([$intitule, $precision] = $decouper($ligne->designation))
                <tr>
                    <td class="rang">{{ $index + 1 }}</td>
                    <td>
                        {{ $intitule }}
                        @if($precision)<span class="precision">{{ $precision }}</span>@endif
                    </td>
                    <td class="qte">{{ $ligne->quantite }}</td>
                    {{-- Un montant nul se lit « à déterminer », comme sur le formulaire de l'étude :
                         les droits d'enregistrement dépendent du capital et ne sont pas connus à
                         l'établissement de la note. --}}
                    <td class="montant">
                        {{ (float) $ligne->montant > 0 ? $fmt($ligne->montant * $ligne->quantite) : 'à déterminer' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="rang">1</td>
                    <td colspan="3" style="font-style: italic; color: #666666;">Aucune prestation facturée</td>
                </tr>
            @endforelse

            {{-- Les deux lignes que le formulaire laisse à compléter : leur montant dépend du
                 nombre de pages de l'acte, inconnu à l'établissement de la note. --}}
            <tr class="variable">
                <td class="rang">{{ $lignes->count() + 1 }}</td>
                <td>
                    Timbres fiscaux
                    <span class="precision">(Montant variable selon le nombre de pages)</span>
                </td>
                <td class="qte">–</td>
                <td class="montant">–</td>
            </tr>
            <tr class="variable">
                <td class="rang">{{ $lignes->count() + 2 }}</td>
                <td>
                    Rôles
                    <span class="precision">(Montant variable selon le nombre de pages)</span>
                </td>
                <td class="qte">–</td>
                <td class="montant">–</td>
            </tr>

            <tr class="total">
                <td colspan="3" style="text-align: right;">TOTAL</td>
                <td class="montant">{{ $fmt($facture->total_chiffres) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="lettres">
        <span class="etiquette">Arrêté en toutes lettres :</span>
        <span class="valeur">{{ $totalEnLettres }}</span>
    </div>

    <div class="courtoisie">
        Je reste à votre entière disposition pour toute information complémentaire et vous prie de
        croire en l'expression de mon dévouement.
    </div>

    <div class="signature">
        <div class="fonction">LE NOTAIRE</div>
        <div class="nom">Maître Ayelama Bah</div>
        <div class="lieu">Notaire à Conakry, Guinée</div>
    </div>
</body>
</html>
