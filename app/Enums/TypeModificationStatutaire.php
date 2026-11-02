<?php

namespace App\Enums;

use App\Contracts\OperationDeSociete;

/**
 * Types de modification statutaire, leur impact et leurs documents (règles 8 et 9 du CR
 * de juillet 2026, `Regles_Gestion_Plateforme_Notariale.docx`, complétées par les notes
 * de l'étude du 2026-08-11 sur le déroulé réel d'une modification).
 *
 * Le type d'acte `SOC-MOD` « Modification de statuts » existait déjà, mais son
 * questionnaire n'avait qu'un champ texte libre « objet de la modification » : rien ne
 * permettait de savoir quels documents produire ni quelles formalités engager. Un
 * changement de gérant **non statutaire** ne touche pas les statuts et ne passe qu'au
 * RCCM — distinction invisible jusqu'ici.
 *
 * Un seul type d'acte paramétré plutôt que sept types distincts (décision validée) : la
 * variante est une donnée du dossier, pas une entrée de nomenclature à maintenir en
 * double avec son questionnaire et ses modèles.
 *
 * ⚠️ **Un dossier porte plusieurs types à la fois** (`donnees['modif.types']`, tableau) :
 * une même assemblée décide couramment une cession de parts, un nouveau gérant et un
 * transfert de siège. Les documents à produire sont l'**union** de leurs exigences —
 * voir `documentsRequisPour()`, qui ne produit qu'un seul procès-verbal.
 */
enum TypeModificationStatutaire: string implements OperationDeSociete
{
    case DenominationSociale = 'denomination_sociale';
    case FormeJuridique      = 'forme_juridique';
    case GerantStatutaire    = 'gerant_statutaire';
    case GerantNonStatutaire = 'gerant_non_statutaire';
    case SiegeSocial         = 'siege_social';
    case CapitalAugmentation = 'capital_augmentation';
    case CapitalDiminution   = 'capital_diminution';
    case CapitalCession      = 'capital_cession';
    case ObjetSocial         = 'objet_social';

    /**
     * Décision de fonctionnement prise en AGO — consentir une hypothèque, par exemple.
     *
     * Ajoutée le 2026-10-28 (addendum v1.2 § 5, `DECISION_GESTION`). C'est la seule opération
     * du catalogue de la spec que le dépôt n'avait pas, et la seule qui **ne touche ni les
     * statuts ni le RCCM** : son effet se borne à produire un procès-verbal et une ligne
     * d'historique. Elle n'est donc portée par aucune mutation de fiche — et c'est voulu, pas
     * un oubli : il n'y a rien à y porter.
     *
     * ⚠️ Son impact RCCM est marqué « à confirmer » par la spec (question Q2 à Maître Bah), avec
     * « non » pour défaut. Le catalogue éditable permettra de le corriger sans redéploiement.
     */
    case DecisionGestion     = 'decision_gestion';

    /**
     * Ordre de rédaction des actes d'un dossier de modification.
     *
     * L'acte de cession précède le procès-verbal qui le constate, les statuts sont mis à
     * jour ensuite, et la déclaration RCCM vient en dernier — c'est l'ordre dans lequel
     * l'étude les rédige, donc celui dans lequel ils doivent apparaître au dossier.
     */
    private const ORDRE_DOCUMENTS = [
        'acte_cession',
        'pv_modification',
        'dnsv',
        'statuts_maj',
        'declaration_rccm',
    ];

    /**
     * Valeur technique — implémentation de {@see VarianteTypeActe}.
     *
     * Ces sept cas sont les variantes du type d'acte `SOC-MOD` : c'est à leur niveau, et non à celui
     * du type d'acte entier, qu'un gabarit se rattache (un acte de cession ne sert qu'aux cessions).
     */
    public function valeur(): string
    {
        return $this->value;
    }

    /**
     * Le code d'opération selon l'addendum v1.2 § 5 — implémentation de {@see OperationDeSociete}.
     */
    public function codeOperation(): string
    {
        return match($this) {
            self::DenominationSociale => 'MOD_DENOMINATION',
            self::FormeJuridique      => 'MOD_FORME',
            self::GerantStatutaire    => 'CHG_GERANT_STAT',
            self::GerantNonStatutaire => 'NOM_GERANT',
            self::SiegeSocial         => 'MOD_SIEGE',
            self::CapitalAugmentation => 'AUG_CAPITAL',
            // ⚠️ **Absente du § 5 de l'addendum**, qui ne la nomme pas — mais la ligne 2 du
            // tableau de validation du 2026-10-28 dit « Augmentation **ou réduction** de
            // capital » : l'étude la pratique, et le dépôt aussi depuis l'origine. Le code est
            // dérivé par symétrie avec `AUG_CAPITAL` ; écart de nommage à remonter à Thierno
            // plutôt qu'à résoudre en supprimant une opération utilisée.
            self::CapitalDiminution   => 'DIM_CAPITAL',
            self::CapitalCession      => 'CESSION_PARTS',
            self::ObjetSocial         => 'MOD_OBJET',
            self::DecisionGestion     => 'DECISION_GESTION',
        };
    }

    /**
     * L'assemblée exigée par cette opération — addendum v1.2 § 2 & § 5.
     */
    public function assemblee(): TypeAssemblee
    {
        return match($this) {
            self::GerantNonStatutaire,
            self::DecisionGestion     => TypeAssemblee::Ago,
            self::DenominationSociale,
            self::FormeJuridique,
            self::GerantStatutaire,
            self::SiegeSocial,
            self::CapitalAugmentation,
            self::CapitalDiminution,
            self::CapitalCession,
            self::ObjetSocial         => TypeAssemblee::Age,
        };
    }


    public function label(): string
    {
        return match($this) {
            self::DecisionGestion     => 'Décision de gestion',
            self::DenominationSociale => 'Modification de dénomination',
            self::FormeJuridique      => 'Modification de forme juridique',
            self::GerantStatutaire    => 'Changement de gérant statutaire',
            self::GerantNonStatutaire => 'Changement de gérant non statutaire',
            self::SiegeSocial         => 'Transfert du siège social',
            self::CapitalAugmentation => 'Augmentation de capital',
            self::CapitalDiminution   => 'Diminution de capital',
            self::CapitalCession      => 'Cession de parts sociales',
            self::ObjetSocial         => "Modification de l'objet social",
        };
    }

    /** Règle 8 — la modification entraîne-t-elle une mise à jour des statuts ? */
    public function impacteStatuts(): bool
    {
        return match($this) {
            // Une décision de fonctionnement ne touche pas au pacte social : elle se prend
            // en AGO et les statuts restent ce qu'ils sont.
            self::DecisionGestion     => false,
            // Le seul cas qui ne touche pas les statuts : un gérant non statutaire n'y est
            // pas nommé, seul le RCCM enregistre le changement.
            self::GerantNonStatutaire => false,
            self::DenominationSociale,
            self::FormeJuridique,
            self::GerantStatutaire,
            self::SiegeSocial,
            self::CapitalAugmentation,
            self::CapitalDiminution,
            self::CapitalCession,
            self::ObjetSocial         => true,
        };
    }

    /** Règle 8 — la modification doit-elle être enregistrée au RCCM ? */
    public function impacteRccm(): bool
    {
        // Toutes, sans exception, d'après le tableau du document.
        return match($this) {
            // **Tranché** par la ligne 6 du tableau de validation signé le 2026-10-28, cochée
            // « Oui » : « Ni les statuts ni le RCCM ne changent ». L'addendum la marquait à
            // confirmer (question Q2) ; elle ne l'est plus. Reste réglable au catalogue.
            self::DecisionGestion     => false,
            self::DenominationSociale,
            self::FormeJuridique,
            self::GerantStatutaire,
            self::GerantNonStatutaire,
            self::SiegeSocial,
            self::CapitalAugmentation,
            self::CapitalDiminution,
            self::CapitalCession,
            self::ObjetSocial => true,
        };
    }

    /**
     * Impose-t-elle une publication au Journal d'Annonces Légales ? — addendum v1.2 § 5.
     *
     * Toutes, sauf la décision de gestion. Le tableau de validation du 2026-10-28 le confirme
     * de deux côtés : la ligne 5 a été **annotée pour ajouter** la publication là où elle
     * manquait (gérant non statutaire), et la ligne 6 — cochée, annotée elle aussi — ne la
     * mentionne pas. Une relectrice qui ajoute la mention où elle manque et ne l'ajoute pas
     * ailleurs répond deux fois.
     */
    public function publicationJal(): bool
    {
        return match($this) {
            self::DecisionGestion     => false,
            self::DenominationSociale,
            self::FormeJuridique,
            self::GerantStatutaire,
            self::GerantNonStatutaire,
            self::SiegeSocial,
            self::CapitalAugmentation,
            self::CapitalDiminution,
            self::CapitalCession,
            self::ObjetSocial         => true,
        };
    }

    /**
     * Ne se combine avec aucune autre opération dans le même dossier — règle C1.
     *
     * Aucune modification statutaire n'est exclusive : une assemblée peut décider
     * plusieurs modifications statutaires à la fois (ex. cession + transfert de siège).
     */
    public function exclusive(): bool
    {
        return false;
    }

    /**
     * La règle est-elle validée par l'étude (✅) ou à confirmer (⚠) ? — addendum v1.2 § 5.
     */
    public function regleValidee(): bool
    {
        return match($this) {
            // ── Non validées : la case « Oui » du tableau est restée vide ────────
            //
            // Ligne 7, non cochée, annotée par Maître Bah : « Rapport du commissaire aux
            // comptes (art. 374 de l'acte uniforme relatif au Droit des Sociétés Commerciales
            // et du GIE, révisé, en vigueur le 5 mai 2014) ». La règle s'est donc **précisée**
            // sans être validée : un document de plus est exigé, les règles de majorité
            // restent inconnues. Le rapport n'est pas encore dans `documentsRequis()` — il y
            // entrera avec les gabarits du lot 5, et l'écart reste visible d'ici là.
            self::FormeJuridique,
            // Lignes 2 et 3, non cochées et sans correction. Le silence n'est pas un accord :
            // les quatorze autres lignes du tableau portent une croix. La règle reste celle
            // que le dépôt applique — elle est simplement signalée comme non relue.
            //
            // ⚠️ La ligne 2 réunit **augmentation et réduction** de capital. La diminution,
            // absente du § 5 de l'addendum, est donc bien une opération de l'étude : c'est ce
            // qui a justifié de l'activer au catalogue. Elle n'est pas pour autant validée.
            self::CapitalDiminution,
            self::CapitalCession      => false,
            // ── Validées par le tableau signé du 2026-10-28 ──────────────────────
            //
            // Ligne 1 (dénomination, siège, objet), ligne 4 (gérant statutaire), ligne 8
            // (cumul dans un même PV) : cochées « Oui », sans correction.
            self::DenominationSociale,
            self::GerantStatutaire,
            self::SiegeSocial,
            self::ObjetSocial,
            // Ligne 2, partie augmentation : cochée pour ce que le dépôt en fait. La seule
            // annotation porte sur l'origine de l'attestation de dépôt — banque si les fonds
            // y sont versés, notaire sinon — et non sur la règle elle-même.
            self::CapitalAugmentation,
            // Ligne 5, cochée « Oui » et **annotée pour ajouter** « Publication obligatoire au
            // journal d'annonces légales ». L'addendum la marquait ⚠ sur ce point précis
            // (question Q1) : la réponse est oui, et elle est écrite.
            self::GerantNonStatutaire,
            // Ligne 6, cochée « Oui », annotée : « Toutes décisions qui n'entraînent pas la
            // modification des statuts doivent être prises en AGO ». L'impact RCCM — ⚠ Q2 de
            // l'addendum — est tranché : ni statuts ni RCCM ne changent.
            self::DecisionGestion     => true,
        };
    }

    /**
     * Une déclaration de souscription et de versement est-elle établie ?
     *
     * Asymétrie explicite des notes de l'étude : « Augmentation de capital : une DNSV est
     * établie pour constater le montant augmenté. Diminution de capital : aucune DNSV
     * n'est requise dans ce cas. » Exposée en méthode propre plutôt que déduite de
     * `documentsRequis()` — c'est cette règle qui conditionne aussi le barème DGI de
     * l'enregistrement DNSV et le bloc de champs correspondant du questionnaire.
     */
    public function exigeDnsv(): bool
    {
        return $this === self::CapitalAugmentation;
    }

    /**
     * Règle 9 — documents nécessaires, par catégorie de pièce.
     *
     * Les clés sont les `modeles_actes.type_document` correspondants : c'est ce vocabulaire
     * qui pilote la génération (voir ActesGeneratorService::genererActesDepuisModeles).
     *
     * @return array<string, string> slug => libellé
     */
    public function documentsRequis(): array
    {
        $pv      = ['pv_modification' => "Procès-verbal de l'assemblée"];
        $statuts = ['statuts_maj'     => 'Statuts mis à jour'];
        // Les notes de l'étude comptent le RCCM parmi les actes **édités** d'une cession,
        // pas seulement parmi les formalités à déposer : la déclaration de modification est
        // rédigée par l'office. Dérivé de impacteRccm() pour que les deux ne divergent pas.
        $rccm    = $this->impacteRccm()
            ? ['declaration_rccm' => 'Déclaration de modification RCCM']
            : [];

        return match($this) {
            // Le document ne mentionne pas de statuts pour un gérant non statutaire —
            // cohérent avec impacteStatuts() : seul le PV est produit.
            self::GerantNonStatutaire => [...$pv, ...$rccm],
            // Ni statuts, ni déclaration RCCM : le procès-verbal **est** l'acte. C'est la
            // seule opération du catalogue dont l'effet se borne au document produit.
            self::DecisionGestion     => $pv,

            self::DenominationSociale,
            self::FormeJuridique,
            self::GerantStatutaire,
            self::SiegeSocial,
            // Une diminution de capital modifie les statuts et passe au RCCM, mais ne
            // donne lieu à aucune DNSV : rien n'est souscrit ni versé.
            self::CapitalDiminution,
            self::ObjetSocial         => [...$pv, ...$statuts, ...$rccm],

            self::CapitalAugmentation => [
                ...$pv,
                'dnsv' => 'Déclaration de souscription et de versement',
                ...$statuts,
                ...$rccm,
            ],

            self::CapitalCession      => [
                'acte_cession' => 'Acte de cession de parts',
                ...$pv,
                ...$statuts,
                ...$rccm,
            ],
        };
    }

    /**
     * Documents à produire pour l'ensemble des modifications décidées par une même
     * assemblée — **union**, pas concaténation : trois résolutions ne donnent qu'un seul
     * procès-verbal et qu'un seul jeu de statuts mis à jour.
     *
     * Trié selon ORDRE_DOCUMENTS pour que l'ordre du dossier ne dépende pas de l'ordre
     * dans lequel le clerc a coché les cases.
     *
     * @param  array<int, self>       $types
     * @return array<string, string>  slug => libellé
     */
    public static function documentsRequisPour(array $types): array
    {
        $documents = [];
        foreach ($types as $type) {
            $documents = [...$documents, ...$type->documentsRequis()];
        }

        // Un document hors ORDRE_DOCUMENTS (ajouté plus tard sans mettre la constante à
        // jour) est conservé en fin de liste plutôt que silencieusement perdu.
        uksort($documents, function (string $a, string $b): int {
            $rangA = array_search($a, self::ORDRE_DOCUMENTS, true);
            $rangB = array_search($b, self::ORDRE_DOCUMENTS, true);

            return ($rangA === false ? PHP_INT_MAX : $rangA)
                <=> ($rangB === false ? PHP_INT_MAX : $rangB);
        });

        return $documents;
    }

    /**
     * Cette modification exige-t-elle la valeur des parts cédées ?
     *
     * Assiette du droit de cession de 2 % (règle 10) — c'est la valeur des parts, pas le
     * capital social.
     */
    public function exigeValeurPartsCedees(): bool
    {
        return $this === self::CapitalCession;
    }

    /**
     * Types que celui-ci **exclut** : deux modifications qui ne peuvent pas être décidées par la
     * même assemblée dans le même dossier.
     *
     * Constaté à l'usage le 2026-08-11 : le formulaire acceptait « Augmentation » **et**
     * « Diminution de capital » cochées ensemble. Les deux écrivent `capital_chiffres` au registre,
     * si bien que {@see \App\Services\SocieteMutationService} retenait arbitrairement la dernière
     * fusionnée — donc l'ordre de déclaration de cet enum décidait du capital de la société, en
     * silence. Le questionnaire produisait de son côté deux « capital après » contradictoires,
     * calculés indépendamment depuis le même capital de départ.
     *
     * Le « coup d'accordéon » (réduction pour absorber les pertes, suivie d'une augmentation) n'est
     * pas ce cas : c'est **une** opération portant **un** capital final, et il fera l'objet d'un
     * type dédié le jour où l'étude en traitera — pas de deux cases qui se contredisent.
     *
     * ⚠️ **Cette relation doit être symétrique** : si A exclut B, B doit exclure A. Sans quoi
     * l'exclusion dépendrait de l'ordre des clics. Un test le verrouille.
     *
     * @return array<int, self>
     */
    public function incompatiblesAvec(): array
    {
        return match($this) {
            self::CapitalAugmentation => [self::CapitalDiminution],
            self::CapitalDiminution   => [self::CapitalAugmentation],

            // Le questionnaire n'a qu'un seul bloc de gérance (un sortant, un entrant) : les deux
            // cases décrivent donc le même changement, qualifié de deux façons exclusives — un
            // gérant est nommé aux statuts ou il ne l'est pas. Les cocher ensemble faisait
            // produire des statuts mis à jour (impacteStatuts() étant une union) que le cas non
            // statutaire ne justifie pas.
            self::GerantStatutaire    => [self::GerantNonStatutaire],
            self::GerantNonStatutaire => [self::GerantStatutaire],

            self::DecisionGestion,
            self::DenominationSociale,
            self::FormeJuridique,
            self::SiegeSocial,
            self::CapitalCession,
            self::ObjetSocial         => [],
        };
    }

    /**
     * Pourquoi ces deux modifications s'excluent — phrase destinée au clerc, affichée en infobulle
     * sur la case grisée et dans le message de blocage. `null` si elles sont compatibles.
     */
    public function motifIncompatibilite(self $autre): ?string
    {
        if (!in_array($autre, $this->incompatiblesAvec(), true)) {
            return null;
        }

        return match(true) {
            in_array($this, [self::CapitalAugmentation, self::CapitalDiminution], true)
                => "Un même capital ne peut pas être augmenté et réduit par la même assemblée : il n'aurait pas de montant final.",
            default
                => "Un gérant est nommé aux statuts ou il ne l'est pas : cette qualification décide si les statuts doivent être mis à jour.",
        };
    }

    /**
     * Paires en conflit dans une sélection, **dédupliquées** — une paire, pas deux fois la même
     * dans les deux sens.
     *
     * @param  array<int, self> $types
     * @return array<int, array{0: self, 1: self, motif: string}>
     */
    public static function conflits(array $types): array
    {
        $conflits = [];
        $vues     = [];

        foreach ($types as $type) {
            foreach ($type->incompatiblesAvec() as $autre) {
                if (!in_array($autre, $types, true)) {
                    continue;
                }

                // Clé indépendante du sens de lecture, pour ne retenir la paire qu'une fois.
                $cle = implode('|', collect([$type->value, $autre->value])->sort()->all());
                if (isset($vues[$cle])) {
                    continue;
                }
                $vues[$cle] = true;

                $conflits[] = [$type, $autre, 'motif' => $type->motifIncompatibilite($autre)];
            }
        }

        return $conflits;
    }

    /**
     * Bloc de champs du questionnaire que ce type de modification ouvre.
     *
     * Les deux changements de gérant partagent un seul bloc : les champs sont les mêmes
     * (gérant sortant, motif, gérant entrant, mandat), seul l'impact statutaire diffère —
     * et c'est cet enum qui le porte, pas le formulaire.
     */
    public function blocQuestionnaire(): string
    {
        return match($this) {
            self::DecisionGestion     => 'modif_decision_gestion',
            self::DenominationSociale => 'modif_denomination',
            self::FormeJuridique      => 'modif_forme',
            self::GerantStatutaire,
            self::GerantNonStatutaire => 'modif_gerant',
            self::SiegeSocial         => 'modif_siege',
            self::CapitalAugmentation => 'modif_capital_augmentation',
            self::CapitalDiminution   => 'modif_capital_diminution',
            self::CapitalCession      => 'modif_cession',
            self::ObjetSocial         => 'modif_objet',
        };
    }

    public function resume(): array
    {
        return [
            'valeur'            => $this->value,
            'label'             => $this->label(),
            'impacteStatuts'    => $this->impacteStatuts(),
            'impacteRccm'       => $this->impacteRccm(),
            'exigeDnsv'         => $this->exigeDnsv(),
            'documentsRequis'   => $this->documentsRequis(),
            'exigeValeurParts'  => $this->exigeValeurPartsCedees(),
            'bloc'              => $this->blocQuestionnaire(),
            // Transportés par `toutes()`, déjà exposé à l'assistant via la prop
            // `typesModification` : le frontend grise les cases exclues sans nouvelle route, et
            // sans redéclarer la règle en JavaScript.
            'incompatibles'     => array_map(fn (self $c) => $c->value, $this->incompatiblesAvec()),
            'motifs'            => array_reduce(
                $this->incompatiblesAvec(),
                fn (array $acc, self $c) => [...$acc, $c->value => $this->motifIncompatibilite($c)],
                [],
            ),
        ];
    }

    /** @return array<int, array> Pour restitution au frontend. */
    public static function toutes(): array
    {
        return array_map(fn (self $c) => $c->resume(), self::cases());
    }

    /**
     * Retrouve le cas depuis le libellé stocké dans le questionnaire.
     *
     * Les questionnaires (`resources/js/data/questionnaires.js`) enregistrent le **libellé
     * affiché** et non un slug — convention en place pour tous les `select` du projet, qui
     * rend `donnees` lisible tel quel dans les modèles Word. On accepte aussi la valeur
     * technique, pour qu'un appel d'API ou un import puisse utiliser l'une ou l'autre.
     */
    public static function depuisLibelle(?string $valeur): ?self
    {
        if (blank($valeur)) {
            return null;
        }

        $valeur = trim($valeur);

        foreach (self::cases() as $cas) {
            if ($cas->label() === $valeur || $cas->value === $valeur) {
                return $cas;
            }
        }

        return null;
    }

    /**
     * Clés de `donnees` portant la sélection — implémentation de {@see VarianteTypeActe}.
     *
     * Deux clés : la migration de données du 2026-08-11 convertit `modif.type` (choix unique)
     * en `modif.types` (tableau), mais un brouillon en cours de saisie ou un import peut encore
     * porter l'ancienne forme. L'ordre est celui du repli.
     *
     * @return array<int, string>
     */
    public static function clesQuestionnaire(): array
    {
        return ['modif.types', 'modif.type'];
    }

    /**
     * Alias de {@see depuisLibelles()} sous le nom du contrat {@see VarianteTypeActe}.
     *
     * Le nom historique est conservé : il est appelé depuis une dizaine d'endroits et parle le
     * vocabulaire de ce cas précis (« libellés » — les questionnaires enregistrent le libellé
     * affiché, pas un slug).
     *
     * @return array<int, self>
     */
    public static function depuisDonnees(mixed $valeur): array
    {
        return self::depuisLibelles($valeur);
    }

    /**
     * Documents imposés par la règle 9 du compte rendu de juillet 2026 — implémentation de
     * {@see VarianteTypeActe}.
     *
     * Rend une liste, contrairement à {@see VarianteDissolution} : ici la référence écrite
     * existe et le document la tabule explicitement. Alias de {@see documentsRequis()}, dont le
     * nom reste celui employé par l'écran Processus.
     *
     * @return array<string, string>
     */
    public function documentsReference(): ?array
    {
        return $this->documentsRequis();
    }

    /**
     * Types de modification d'un dossier, depuis `donnees['modif.types']`.
     *
     * Accepte un **tableau** (forme actuelle) comme une **chaîne** (ancien `modif.type`,
     * choix unique) : la migration de données du 2026-08-11 convertit les questionnaires
     * existants, mais un brouillon en cours de saisie ou un import peut encore porter
     * l'ancienne forme — la refuser bloquerait le dossier sans recours.
     *
     * Les valeurs non reconnues sont ignorées plutôt que de faire échouer l'ensemble : un
     * libellé mal orthographié doit remonter comme « type de modification manquant » par
     * ReglesSocieteService, message actionnable, et non comme une erreur d'enum.
     *
     * @return array<int, self> dédupliqué, dans l'ordre de déclaration de l'enum
     */
    public static function depuisLibelles(mixed $valeur): array
    {
        $valeurs = match (true) {
            is_array($valeur)  => $valeur,
            is_string($valeur) => [$valeur],
            default            => [],
        };

        $trouves = [];
        foreach ($valeurs as $item) {
            if (!is_string($item)) {
                continue;
            }
            $cas = self::depuisLibelle($item);
            if ($cas) {
                $trouves[$cas->value] = $cas;
            }
        }

        // Ordre de l'enum plutôt que celui de saisie : la liste des documents et l'affichage
        // doivent être stables d'un dossier à l'autre.
        return array_values(array_filter(
            self::cases(),
            fn (self $cas) => isset($trouves[$cas->value]),
        ));
    }
}
