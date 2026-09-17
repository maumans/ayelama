<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Questionnaire SARL — {{ $dossier->reference }}</title>
    <style>
        @page {
            margin: 14mm 18mm 14mm 18mm;
            size: A4 portrait;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.32;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .page {
            width: 100%;
        }
        .titre-principal {
            font-size: 13.5pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .sous-titre {
            font-size: 7.8pt;
            text-align: center;
            line-height: 1.25;
            margin-bottom: 6px;
        }
        .separateur-titre {
            border: none;
            border-top: 1.5px solid #000;
            margin: 5px 0 10px 0;
        }
        .section-item {
            margin-bottom: 8px;
        }
        .label {
            font-weight: bold;
            font-size: 9.5pt;
        }
        .valeur {
            font-size: 9.5pt;
            padding-left: 4px;
        }
        .valeur-forte {
            font-size: 9.5pt;
            font-weight: bold;
            padding-left: 4px;
        }
        .texte-explicatif {
            font-size: 8.5pt;
            text-align: justify;
            margin-top: 3px;
            line-height: 1.28;
        }
        .liste-puces {
            margin-left: 18px;
            margin-top: 2px;
        }
        .puce-item {
            font-size: 9.5pt;
            margin-bottom: 1.5px;
        }
        .pieces-fournir {
            margin-top: 6px;
        }
        .pieces-titre {
            font-weight: bold;
            font-size: 9.5pt;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .piece-explication {
            font-size: 8.5pt;
            text-align: justify;
            margin-bottom: 4px;
            line-height: 1.26;
        }
        .fleche {
            font-size: 9pt;
            margin-right: 2px;
        }
        .checklist-gerant {
            margin-left: 20px;
            margin-top: 3px;
        }
        .check-item {
            font-size: 8.8pt;
            margin-bottom: 2px;
        }
        .carre-check {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1.2px solid #000;
            vertical-align: middle;
            margin-left: 4px;
        }
        .page-break {
            page-break-before: always;
        }
        .bandeau-gris {
            background-color: #d9d9d9;
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
            padding: 3px 6px;
            margin: 8px 0 7px 0;
            letter-spacing: 0.3px;
        }
        .bloc-info {
            margin-bottom: 7px;
        }
        .sous-titre-info {
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .texte-info {
            font-size: 8.5pt;
            line-height: 1.26;
        }
        .info-puce {
            margin-bottom: 2.5px;
        }
        .zone-signatures {
            margin-top: 35px;
        }
        .table-signatures {
            width: 100%;
            border-collapse: collapse;
        }
        .table-signatures td {
            vertical-align: bottom;
            font-size: 9.5pt;
        }
        .ligne-signature {
            display: inline-block;
            width: 200px;
            border-bottom: 1px solid #000;
            height: 1px;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>

    <!-- PAGE 1 -->
    <div class="page">
        <div class="titre-principal">QUESTIONNAIRE SOCIETE A RESPONSABILITE LIMITEE</div>
        <div class="sous-titre">
            (A renseigner, dater, signer et retourner à l'Office Notarial {{ $officeNom ?? 'Maître Ayelama BAH' }}, Notaire à la Résidence à RATOMA, Nongo (Immeuble VISTA BANK, 3<sup>ème</sup> Etage, BP 2668), {{ $officeEmail ?? 'ayelama.bah@notaire-guinee.com' }})
        </div>
        <hr class="separateur-titre">

        <div class="section-item">
            <span class="label">a) Dénomination de la société :</span>
            @if(!empty($denomination))
                <span class="valeur-forte">{{ $denomination }}</span>
            @endif
        </div>

        <div class="section-item">
            <span class="label">b) Adresse du siège social :</span>
            @if(!empty($siegeSocial))
                <span class="valeur">{{ $siegeSocial }}</span>
            @endif
        </div>

        <div class="section-item">
            <span class="label">c) Adresse Email de la société :</span>
            @if(!empty($emailSociete))
                <span class="valeur">{{ $emailSociete }}</span>
            @endif
        </div>

        <div class="section-item">
            <span class="label">d) Montant du Capital social :</span>
            @if(!empty($capitalSocial))
                <span class="valeur">{{ $capitalSocial }}</span>
            @endif
            <div class="texte-explicatif">
                Aucun capital minimum n’est requis par la loi (décret n° 124 du 30 mai 2014 du Président de la République). Le capital social est divisé en parts sociale. Le capital social à libérer, peut être déposé à la Comptabilité du Notaire ou dans un compte d’attente ouvert à cet effet dans une banque de la place ; les parties remettront alors au Notaire une attestation délivrée par ladite banque indiquant qu’il a été effectivement libéré.
            </div>
        </div>

        <div class="section-item">
            <span class="label">e) Répartition du Capital entre les associés :</span>
            <div class="liste-puces">
                @if(!empty($repartitionAssocies))
                    @foreach($repartitionAssocies as $associe)
                        <div class="puce-item">• {{ $associe }}</div>
                    @endforeach
                @else
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                @endif
            </div>
        </div>

        <div class="section-item">
            <span class="label">f) Objet social (lister les secteurs d’activités) :</span>
            <div class="liste-puces">
                @if(!empty($objetSocialLignes))
                    @foreach($objetSocialLignes as $ligne)
                        <div class="puce-item">• {{ $ligne }}</div>
                    @endforeach
                @else
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                    <div class="puce-item">•</div>
                @endif
            </div>
        </div>

        <div class="section-item">
            <span class="label">g) Désigner le ou les Gérant(s) :</span>
            @if(!empty($gerants))
                <div class="liste-puces">
                    @foreach($gerants as $gerant)
                        <div class="puce-item">• {{ $gerant }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="pieces-fournir">
            <div class="pieces-titre">PIECES A FOURNIR:</div>
            <div class="piece-explication">
                <span class="fleche">&#8680;</span> Pour chaque associé personne physique, fournir : une copie de la pièce d’identité ou du passeport, un numéro de téléphone, un email, un certificat de résidence, deux (02) photos d’identité et la situation matrimoniale.
            </div>
            <div class="piece-explication">
                <span class="fleche">&#8680;</span> Pour chaque associé personne morale, fournir : les statuts et une copie de la déclaration d’immatriculation au Registre du Commerce et du Crédit Mobilier et ou un PV des délibérations autorisant l’ouverture d’une filiale ou succursale en République de Guinée, une copie de la pièce d’identité ou du passeport du représentant légal, un numéro de téléphone et email de la société.
            </div>
            <div class="piece-explication">
                <span class="fleche">&#8680;</span> Pour chaque Gérant(s), fournir une copie de la pièce d’identité ou du passeport, un numéro de téléphone, un certificat de résidence, deux (02) photos d’identité et la situation matrimoniale.
            </div>
            <div class="checklist-gerant">
                <div class="check-item"><span class="bullet">•</span> La pièce d’identité <span class="carre-check"></span></div>
                <div class="check-item"><span class="bullet">•</span> Le certificat de résidence <span class="carre-check"></span></div>
                <div class="check-item"><span class="bullet">•</span> Deux (02) photos d’identité <span class="carre-check"></span></div>
                <div class="check-item"><span class="bullet">•</span> La situation matrimoniale : <span class="valeur">{{ $situationMatrimonialeGerant ?? '' }}</span></div>
                <div class="check-item"><span class="bullet">•</span> Le numéro de téléphone : <span class="valeur">{{ $telephoneGerant ?? '' }}</span></div>
                <div class="check-item"><span class="bullet">•</span> L’adresse Email : <span class="valeur">{{ $emailGerant ?? '' }}</span></div>
            </div>
        </div>
    </div>

    <!-- PAGE 2 -->
    <div class="page page-break">
        <div class="section-item" style="margin-top: 4px;">
            <span class="label">h) Désigner les organes de contrôles :</span>
            <div class="texte-info" style="margin-top: 4px; margin-bottom: 5px;">
                Les associés peuvent nommer un ou plusieurs Commissaires aux Comptes.
            </div>
            <div class="piece-explication" style="margin-left: 15px;">
                <span class="fleche">&#8680;</span> <strong>Commissaire aux comptes (Facultatif) :</strong>
                <span class="valeur">{{ $commissaireComptes ?? '' }}</span>
            </div>
        </div>

        <div class="section-item" style="margin-top: 8px; margin-bottom: 8px;">
            <span class="label">i) La constitution de la société est obligatoirement publiée dans un Journal d'Annonces Légales.</span>
        </div>

        <div class="bandeau-gris">
            INFORMATIONS COMPLEMENTAIRES ET DIVERS
        </div>

        <div class="bloc-info">
            <div class="sous-titre-info">REGIME FISCAL DE FAVEUR :</div>
            <div class="texte-info">
                Si vous bénéficiez d’un régime fiscal de faveur, veuillez produire le décret ou l’arrêté d’agrément.
                @if(!empty($regimeFiscalReference))
                    <br><em>(Régime déclaré : {{ $regimeFiscalReference }})</em>
                @endif
            </div>
        </div>

        <div class="bloc-info">
            <div class="sous-titre-info">RETRAIT DU CAPITAL APRES CONSTITUTION DE LA SOCIETE :</div>
            <div class="texte-info">
                <div class="info-puce">• S’il a été déposé chez le notaire, il vous délivrera un chèque à l’ordre de la société, après déduction de ses frais et émoluments.</div>
                <div class="info-puce">• S’il a été déposé à la banque, le notaire délivrera un certificat de déblocage de fonds.</div>
                <div class="info-puce" style="text-align: justify;">• <strong>NB :</strong> Les fonds déposés chez le Notaire à titre de libération du capital social sont indisponibles jusqu’au jour de l’immatriculation de la société au Registre du Commerce et du crédit Mobilier. Dans le cas où la société ne serait pas immatriculée au Registre du Commerce et du Crédit Mobilier dans le délai de six (06) mois à compter du premier dépôt, les apporteurs peuvent, soit individuellement, soit par mandataire les représentant collectivement, demander au Président du Tribunal de Commerce l’autorisation de retirer le montant de leurs apports.</div>
            </div>
        </div>

        <div class="bloc-info">
            <div class="sous-titre-info">OUVERTURE DE COMPTE :</div>
            <div class="texte-info">
                Vous pouvez, après retrait des fonds, procéder à l’ouverture d’un compte bancaire au nom de la société. Il vous faut alors fournir une copie des statuts, une copie de l’immatriculation au RCCM et un exemplaire du Journal d’Annonces Légales.
            </div>
        </div>

        <div class="bloc-info">
            <div class="sous-titre-info">FORMALITES ADMINISTRATIVES ET FISCALES :</div>
            <div class="texte-info" style="text-align: justify;">
                Toute société régulièrement constituée et établie au Guinée est tenue d’être déclarée auprès de l’Administration fiscale. Ce dernier constitue la déclaration d’existence de la société au fichier des contribuables. Le numéro délivré vous permet de faire vos déclarations de TVA et doit figurer sur toutes vos factures, papier en-tête, quittance, reçus, etc.
            </div>
        </div>

        <div class="bloc-info">
            <div class="sous-titre-info">REGISTRES SOCIAUX</div>
            <div class="texte-info" style="text-align: justify;">
                Les procès-verbaux des Assemblées doivent être établis sur un registre spécial tenu au siège social de la société et obligatoirement coté et paraphé par le Président du Tribunal de Commerce de Conakry. L’Office Notarial, dans le but de vous aider à répondre à cette exigence pourra vous accompagner pour l'obtention dudit registre.
            </div>
        </div>

        <div class="bloc-info">
            <div class="sous-titre-info">FRAIS ET HONORAIRES</div>
            <div class="texte-info">
                Les frais et honoraires sont de cinq millions cinq-cents mille (5.500.000) Francs Guinéens.
            </div>
        </div>

        <div class="zone-signatures">
            <table class="table-signatures">
                <tr>
                    <td style="width: 50%;">
                        <strong>Date :</strong> <span class="ligne-signature"></span>
                    </td>
                    <td style="width: 50%; text-align: right;">
                        <strong>Signature :</strong> <span class="ligne-signature"></span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>
