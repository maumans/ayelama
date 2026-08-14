<?php

namespace App\Enums;

use App\Contracts\VarianteTypeActe;

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
enum TypeModificationStatutaire: string implements VarianteTypeActe
{
    case GerantStatutaire    = 'gerant_statutaire';
    case GerantNonStatutaire = 'gerant_non_statutaire';
    case SiegeSocial         = 'siege_social';
    case CapitalAugmentation = 'capital_augmentation';
    case CapitalDiminution   = 'capital_diminution';
    case CapitalCession      = 'capital_cession';
    case ObjetSocial         = 'objet_social';

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

    public function label(): string
    {
        return match($this) {
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
            // Le seul cas qui ne touche pas les statuts : un gérant non statutaire n'y est
            // pas nommé, seul le RCCM enregistre le changement.
            self::GerantNonStatutaire => false,
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
