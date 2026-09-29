<?php

namespace App\Services;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Enums\FormeSociete;
use App\Enums\MotifRepresentation;
use App\Enums\TypeModificationStatutaire;
use App\Enums\VarianteDissolution;
use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Support\VariantesTypeActe;

/**
 * Contrôle des règles légales applicables à la constitution d'une société
 * (`Regles_Gestion_Plateforme_Notariale.docx`, CR de juillet 2026).
 *
 * Ces règles ne sont pas des préférences d'interface : produire les statuts d'une SA
 * dotée d'un capital inférieur au minimum légal, ou sans commissaire aux comptes, expose
 * l'office. Elles sont donc **bloquantes** — branchées dans
 * `DossierStepService::erreursDeConstitution()`, au même titre que l'accord signé du
 * client, et remontent automatiquement dans le panneau des conditions requises.
 *
 * Un seul endroit lit le questionnaire (`soc.forme`, `soc.capital_chiffres`,
 * `soc.commissaire_titulaire`) et les parties : les règles ne doivent pas se disperser
 * dans les contrôleurs.
 */
class ReglesSocieteService
{
    /**
     * Manquements aux règles, au format attendu par `ValidationException::withMessages()`.
     *
     * @return array<string, string[]>
     */
    public function anomalies(Dossier $dossier): array
    {
        // Un bail, une vente ou une procuration n'a pas de forme juridique : ces règles
        // ne s'appliquent qu'aux dossiers de société.
        if ($dossier->typeActe?->categorie !== CategorieActe::Societe) {
            return [];
        }

        // Modification de statuts : ses propres règles (8, 9 et l'assiette du droit de
        // cession), distinctes des règles de constitution.
        if ($dossier->typeActe?->code === 'SOC-MOD') {
            return $this->verifierModificationStatutaire($dossier);
        }

        // Dissolution-liquidation : ses propres règles, symétriques de celles de la
        // modification. Comme elle, ce n'est pas une constitution — lui appliquer le capital
        // minimum ou le nombre d'associés n'aurait aucun sens.
        if ($dossier->typeActe?->code === 'SOC-DIS') {
            return $this->verifierDissolution($dossier);
        }

        $forme = $this->forme($dossier);
        if (!$forme) {
            // Aucune forme identifiable : soit le type d'acte n'en désigne pas une
            // (`SOC-MOD` modification, `SOC-DIS` dissolution — ces règles de
            // *constitution* ne s'y appliquent pas), soit la forme est inconnue. Dans les
            // deux cas, ne rien bloquer : refuser d'avancer un dossier de dissolution au
            // motif qu'il n'a pas de capital minimum serait absurde.
            return [];
        }

        return array_merge(
            $this->verifierDenominationUnique($dossier),
            $this->verifierCapitalMinimum($dossier, $forme),
            $this->verifierNombreAssocies($dossier, $forme),
            $this->verifierCommissaireAuxComptes($dossier, $forme),
            $this->verifierCapaciteJuridique($dossier, $forme),
        );
    }

    /**
     * Indique si un dossier a dépassé l'étape des formalités (donc est en Expédition ou Archivé).
     * C'est le critère pour certifier qu'une société est officiellement créée (immatriculée au RCCM / API).
     */
    public function aDepasseFormalites(?Dossier $dossier): bool
    {
        if (! $dossier || ! $dossier->etape) {
            return false;
        }

        return $dossier->etape->ordre() > EtapeDossier::Formalites->ordre();
    }

    /**
     * Recherche un conflit de dénomination avec une société déjà certifiée créée.
     *
     * Deux dossiers en cours de constitution peuvent porter la même dénomination tant qu'aucun
     * n'a dépassé l'étape des formalités. Dès qu'un dossier dépasse les formalités (passage en
     * Expédition ou Clôture), la société est réputée officiellement créée (enregistrée au RCCM / API).
     * Toute création ultérieure ou avancement d'un dossier homonyme est alors bloqué avec un message
     * explicite (non pas un simple « déjà existant »).
     *
     * @return array{message: string, dossier: ?Dossier, societe: ?Societe}|null
     */
    public function conflitDenomination(string $denomination, ?int $exclureDossierId = null): ?array
    {
        $cible = $this->normaliser($denomination);
        if ($cible === '') {
            return null;
        }

        // 1. Dossiers de constitution ayant dépassé l'étape des formalités
        $questionnaireConflit = Questionnaire::query()
            ->when($exclureDossierId, fn ($q) => $q->whereNot('dossier_id', $exclureDossierId))
            ->with('dossier:id,reference,type_acte_id,etape', 'dossier.typeActe:id,code')
            ->get()
            ->filter(fn (Questionnaire $q) => $this->normaliser($q->donnees['soc.denomination'] ?? null) === $cible)
            ->filter(fn (Questionnaire $q) => FormeSociete::depuisCodeTypeActe($q->dossier?->typeActe?->code) !== null)
            ->first(fn (Questionnaire $q) => $this->aDepasseFormalites($q->dossier));

        if ($questionnaireConflit && $questionnaireConflit->dossier) {
            $ref = $questionnaireConflit->dossier->reference;

            return [
                'message' => sprintf(
                    'Cette dénomination sociale ne peut pas être utilisée : elle est déjà immatriculée par la société du dossier %s dont les formalités de création ont été accomplies.',
                    $ref
                ),
                'dossier' => $questionnaireConflit->dossier,
                'societe' => null,
            ];
        }

        // 2. Société au registre dont le dossier a dépassé les formalités, ou déjà immatriculée (hors dossier / RCCM)
        $societeConflit = Societe::with('dossier:id,reference,etape')
            ->get()
            ->first(function (Societe $s) use ($cible, $exclureDossierId) {
                if ($this->normaliser($s->denomination) !== $cible) {
                    return false;
                }
                if ($exclureDossierId && $s->dossier_id === $exclureDossierId) {
                    return false;
                }
                if ($s->dossier) {
                    return $this->aDepasseFormalites($s->dossier);
                }

                return filled($s->rccm_numero) || ! $s->dossier_id;
            });

        if ($societeConflit) {
            $ref = $societeConflit->dossier?->reference;
            $msg = $ref
                ? sprintf('Cette dénomination sociale ne peut pas être utilisée : elle est déjà immatriculée par la société du dossier %s dont les formalités de création ont été accomplies.', $ref)
                : sprintf('Cette dénomination sociale ne peut pas être utilisée : la société « %s » est déjà immatriculée au registre (formalités accomplies).', $societeConflit->denomination);

            return [
                'message' => $msg,
                'dossier' => $societeConflit->dossier,
                'societe' => $societeConflit,
            ];
        }

        return null;
    }

    /**
     * Règle 4 — « pas de répétition de nom de société, la dénomination doit être UNIQUE ».
     *
     * Deux dossiers en cours de constitution peuvent porter la même dénomination tant qu'aucun
     * n'a dépassé l'étape des formalités. Un conflit n'apparaît que lorsqu'un dossier a dépassé
     * les formalités (immatriculation effective).
     */
    private function verifierDenominationUnique(Dossier $dossier): array
    {
        $denomination = $this->donnee($dossier, 'soc.denomination');
        if (blank($denomination)) {
            return [];
        }

        $conflit = $this->conflitDenomination((string) $denomination, $dossier->id);
        if ($conflit !== null) {
            return ['soc_denomination' => [$conflit['message']]];
        }

        return [];
    }

    /**
     * Comparaison insensible à la casse, aux accents de casse et aux espaces multiples.
     *
     * Délègue à {@see Societe::normaliserDenomination()} : le backfill du registre doit
     * rapprocher les questionnaires exactement comme cette règle les compare, sinon une
     * dénomination jugée unique ici créerait un doublon au registre.
     */
    private function normaliser(mixed $valeur): string
    {
        return Societe::normaliserDenomination($valeur);
    }

    /** Règle 1 — capital minimum par forme. */
    private function verifierCapitalMinimum(Dossier $dossier, FormeSociete $forme): array
    {
        $minimum = $forme->capitalMinimum();
        if ($minimum === null) {
            return [];
        }

        $capital = (int) $this->donnee($dossier, 'soc.capital_chiffres');
        if ($capital >= $minimum) {
            return [];
        }

        return ['soc_capital' => [sprintf(
            'Capital insuffisant : une %s exige au moins %s GNF (capital saisi : %s GNF).',
            $forme->value,
            number_format($minimum, 0, ',', ' '),
            number_format($capital, 0, ',', ' '),
        )]];
    }

    /** Règle 2 — formes à associé unique. */
    private function verifierNombreAssocies(Dossier $dossier, FormeSociete $forme): array
    {
        $nombre = $this->associes($dossier)->count();

        // Aucun associé saisi : le contrôle des pièces s'en charge déjà, ne pas doubler
        // le message à ce stade de la saisie.
        if ($nombre === 0) {
            return [];
        }

        if ($forme->exigeAssocieUnique() && $nombre > 1) {
            return ['soc_associes' => [sprintf(
                'Une %s ne peut avoir qu\'un seul associé (%d saisis). Pour plusieurs associés, choisissez la forme pluripersonnelle correspondante.',
                $forme->value,
                $nombre,
            )]];
        }

        if (!$forme->admetAssocieUnique() && $nombre === 1) {
            return ['soc_associes' => [sprintf(
                'Une %s ne peut pas être constituée avec un seul associé. Les formes unipersonnelles sont SASU, SARLU et SAU.',
                $forme->value,
            )]];
        }

        return [];
    }

    /** Règle 5 — commissaire aux comptes obligatoire en SA. */
    private function verifierCommissaireAuxComptes(Dossier $dossier, FormeSociete $forme): array
    {
        if (!$forme->exigeCommissaireAuxComptes()) {
            return [];
        }

        $dossier->loadMissing('parties');
        if ($dossier->parties->where('role', 'commissaire_titulaire')->isNotEmpty()) {
            return [];
        }

        return ['soc_commissaire' => [sprintf(
            'Un commissaire aux comptes titulaire est obligatoire pour une %s.',
            $forme->value,
        )]];
    }

    /**
     * Règle 7 — mineurs et incapables.
     *
     * Trois exigences distinctes, souvent confondues :
     *   - **signer** un acte requiert 18 ans, sans exception ;
     *   - être **associé** d'une société de capitaux n'a pas d'âge minimum, mais un mineur
     *     doit être représenté par son tuteur légal ;
     *   - être **associé** d'une société de personnes requiert la majorité — la
     *     responsabilité y étant illimitée et solidaire, elle ne peut engager un mineur.
     */
    private function verifierCapaciteJuridique(Dossier $dossier, FormeSociete $forme): array
    {
        $exigeMajorite = $forme->nature()->exigeMajoritePourEtreAssocie();
        $mineursSansTuteur = [];
        $mineursInterdits  = [];

        foreach ($this->associes($dossier) as $partie) {
            $client = $partie->client;
            // Sans fiche client ni date de naissance, l'âge est inconnu : on ne bloque pas
            // sur une supposition. Le champ reste facultatif au questionnaire.
            if (!$client || !$client->date_naissance) {
                continue;
            }

            if ($client->date_naissance->age >= 18) {
                continue;
            }

            if ($exigeMajorite) {
                $mineursInterdits[] = $partie->nom;
                continue;
            }

            // Société de capitaux : toléré si le mineur est **effectivement représenté**.
            //
            // La tolérance s'obtenait jusqu'au 2026-09-25 en tapant deux mots dans
            // `clients.representant_legal` / `representant_qualite` — deux champs de texte
            // libre conçus pour les personnes morales, détournés faute de mieux. Aucune
            // personne réelle derrière, aucune pièce, et rien dans l'acte produit : une case à
            // cocher déguisée. Elle exige désormais un tuteur désigné au dossier, avec sa
            // fiche, sa CNI et sa mention de comparution.
            //
            // Mesuré avant bascule : zéro fiche physique portant `representant_legal`, zéro
            // associé mineur, zéro dossier impacté. Le changement était donc sans effet de
            // bord sur l'existant.
            if ($partie->representation_motif !== MotifRepresentation::Legale
                || $partie->represente_par_partie_id === null) {
                $mineursSansTuteur[] = $partie->nom;
            }
        }

        $erreurs = [];

        if ($mineursInterdits) {
            $erreurs['capacite_juridique'] = [sprintf(
                'Un mineur ne peut pas être associé d\'une %s (%s) : la responsabilité y est illimitée et solidaire.',
                $forme->value,
                implode(', ', $mineursInterdits),
            )];
        }

        if ($mineursSansTuteur) {
            $erreurs['representation'] = [sprintf(
                'Associé mineur non représenté : %s. Cochez « Se fait représenter » sur cette personne '
                . 'et désignez son tuteur ou curateur — la fiche client ne suffit plus.',
                implode(', ', $mineursSansTuteur),
            )];
        }

        return $erreurs;
    }

    /**
     * Règles 8 à 10 — modification de statuts.
     *
     * Un dossier de modification porte **plusieurs** types depuis le 2026-08-11 (une même
     * assemblée décide couramment une cession, un nouveau gérant et un transfert de siège) :
     * chaque contrôle s'applique donc au sous-ensemble concerné, et toutes les anomalies sont
     * remontées ensemble plutôt qu'une à la fois — corriger un dossier en six allers-retours
     * n'est pas un service rendu.
     */
    private function verifierModificationStatutaire(Dossier $dossier): array
    {
        $types = $this->typesModification($dossier);

        if ($types === []) {
            return ['modif_types' => [
                'Le type de modification doit être précisé : il détermine les actes à produire, les formalités à engager et les droits à percevoir.',
            ]];
        }

        // Contrôlées en premier et **seules** : tant que la sélection se contredit, les contrôles
        // de détail porteraient sur des blocs qui n'ont pas à coexister, et noieraient le vrai
        // problème sous des messages secondaires.
        $incompatibilites = $this->verifierIncompatibilites($types);
        if ($incompatibilites !== []) {
            return $incompatibilites;
        }

        return array_merge(
            $this->verifierSocieteRattachee($dossier),
            $this->verifierPiecesConstitutives($dossier),
            $this->verifierAssemblee($dossier),
            $this->verifierCession($dossier, $types),
            $this->verifierCapital($dossier, $types),
            $this->verifierGerance($dossier, $types),
        );
    }

    /**
     * Règles d'un dossier de dissolution-liquidation.
     *
     * ⚠️ **Aucun délai n'est contrôlé ici, et c'est délibéré.** Durée du mandat de liquidateur,
     * clôture sous trois ans, radiation sous un mois : ces chiffres circulent mais ne viennent
     * d'aucune source que le dépôt puisse citer — le compte rendu de juillet 2026 ne mentionne
     * ni dissolution, ni liquidation, ni radiation. Les transformer en règle bloquante
     * empêcherait l'étude de traiter un dossier parfaitement régulier, au nom d'une contrainte
     * inventée. Ils vivent dans {@see \App\Enums\JalonLiquidation}, marqués à vérifier, et
     * **alertent** sans jamais bloquer.
     *
     * Ce qui est contrôlé ici ne relève pas du droit guinéen mais de la **cohérence interne**
     * de données que l'application produit elle-même : on ne clôture pas la liquidation d'une
     * société qui n'a jamais été dissoute, et on ne dissout pas deux fois la même.
     */
    private function verifierDissolution(Dossier $dossier): array
    {
        $phases = array_values(array_filter(
            VariantesTypeActe::duDossier($dossier),
            fn ($v) => $v instanceof VarianteDissolution,
        ));

        if ($phases === []) {
            return ['dissolution_phase' => [
                'La phase doit être précisée : dissolution anticipée ou clôture de la liquidation. '
                . 'Elle détermine les actes à produire et le statut porté à la fiche société.',
            ]];
        }

        return array_merge(
            $this->verifierSocieteRattachee($dossier),
            $this->verifierPiecesConstitutives($dossier),
            $this->verifierCoherenceCycleVie($dossier, $phases[0]),
            $this->verifierAssembleeDissolution($dossier, $phases[0]),
            $this->verifierLiquidateur($dossier, $phases[0]),
        );
    }

    /**
     * La phase demandée est-elle compatible avec l'état actuel de la fiche société ?
     *
     * Le contrôle ne s'exerce que si le dossier est rattaché au registre : une société saisie à
     * la main n'a pas d'historique dans l'application, et exiger qu'elle en ait un bloquerait
     * les dissolutions de sociétés constituées ailleurs — que l'étude traite couramment.
     *
     * Le message dit **quoi faire**, pas seulement ce qui ne va pas : une règle qui refuse sans
     * indiquer la sortie se contourne par une saisie à la main, ce qui est exactement ce qu'on
     * cherche à éviter.
     */
    private function verifierCoherenceCycleVie(Dossier $dossier, VarianteDissolution $phase): array
    {
        $societe = $dossier->societe;

        if (!$societe) {
            return [];
        }

        $actuel = $societe->statut;
        $requis = $phase->statutRequis();

        if ($actuel === $requis) {
            return [];
        }

        // Le dossier en cours a déjà posé son effet : l'état de la fiche est celui d'après, pas
        // celui d'avant, et le signaler rendrait le dossier impossible à faire ré-avancer après
        // un renvoi en correction.
        //
        // ⚠️ La condition porte sur **l'étape de ce dossier-ci**, pas seulement sur l'état de la
        // fiche. Une première version comparait `$actuel === $phase->statutApres()`, ce qui
        // exemptait aussi une société dissoute par *un autre* dossier : la règle « on ne dissout
        // pas deux fois » était alors désarmée en silence. Deux tests l'ont attrapé.
        // `aDepasseFormalites()` est le seuil exact auquel l'effet est appliqué.
        if ($actuel === $phase->statutApres() && $this->aDepasseFormalites($dossier)) {
            return [];
        }

        $message = match ($phase) {
            VarianteDissolution::Dissolution => sprintf(
                'La société « %s » est déjà %s au registre%s. Une seconde dissolution ne peut pas être prononcée : '
                . "pour en clôturer la liquidation, ouvrez un dossier en phase « %s ».",
                $societe->denomination,
                mb_strtolower($actuel->label()),
                $societe->dissolution_at ? ' depuis le ' . $societe->dissolution_at->format('d/m/Y') : '',
                VarianteDissolution::ClotureLiquidation->label(),
            ),
            VarianteDissolution::ClotureLiquidation => sprintf(
                'La société « %s » est %s au registre : on ne peut pas clôturer une liquidation qui n\'a pas été ouverte. '
                . "Ouvrez d'abord un dossier en phase « %s », ou corrigez le statut de la fiche s'il est erroné.",
                $societe->denomination,
                mb_strtolower($actuel->label()),
                VarianteDissolution::Dissolution->label(),
            ),
        };

        return ['dissolution_cycle_vie' => [$message]];
    }

    /**
     * L'assemblée de la phase en cours doit être datée.
     *
     * La clé dépend de la phase ({@see VarianteDissolution::cleDateAssemblee()}) : les deux
     * assemblées sont séparées de mois ou d'années, chacune est datée dans son propre dossier.
     * C'est cette date qui alimente `dissolution_at` / `cloture_liquidation_at` au registre, et
     * depuis laquelle les échéances de liquidation se calculent.
     */
    private function verifierAssembleeDissolution(Dossier $dossier, VarianteDissolution $phase): array
    {
        if (filled($this->donnee($dossier, $phase->cleDateAssemblee()))) {
            return [];
        }

        return ['dissolution_assemblee' => [
            sprintf(
                "La date de l'assemblée doit être renseignée : elle date l'acte, et c'est depuis elle que "
                . 'court le suivi de la liquidation au registre (phase « %s »).',
                $phase->label(),
            ),
        ]];
    }

    /**
     * Un liquidateur doit être nommé par l'assemblée de dissolution.
     *
     * Phase 1 seulement : à la clôture, le liquidateur est déjà en fonction et figure au
     * registre — le redemander ferait ressaisir une information que l'application détient.
     */
    private function verifierLiquidateur(Dossier $dossier, VarianteDissolution $phase): array
    {
        if ($phase !== VarianteDissolution::Dissolution) {
            return [];
        }

        if (filled($this->donnee($dossier, 'liquidateur.prenom_nom'))) {
            return [];
        }

        return ['dissolution_liquidateur' => [
            "Le liquidateur doit être nommé : c'est lui qui représente la société pendant toute la "
            . 'liquidation et qui signe les actes.',
        ]];
    }

    /**
     * Deux modifications qui ne peuvent pas être décidées par la même assemblée.
     *
     * L'assistant grise déjà les cases exclues, mais il n'est pas le seul chemin vers
     * `questionnaires.donnees` : `PATCH /dossiers/{ref}/questionnaire`, la conversion d'une demande
     * externe, un brouillon enregistré avant ce garde-fou et la migration
     * `modif.type → modif.types` y écrivent aussi. Une règle de droit ne peut pas ne vivre que dans
     * le formulaire.
     *
     * @param array<int, TypeModificationStatutaire> $types
     */
    private function verifierIncompatibilites(array $types): array
    {
        $conflits = TypeModificationStatutaire::conflits($types);

        if ($conflits === []) {
            return [];
        }

        return ['modif_incompatibles' => array_map(
            fn (array $paire) => sprintf(
                '« %s » et « %s » ne peuvent pas être décidées par la même assemblée. %s',
                $paire[0]->label(),
                $paire[1]->label(),
                $paire['motif'],
            ),
            $conflits,
        )];
    }

    /**
     * Le dossier doit désigner la société qu'il modifie.
     *
     * `societe_id` (fiche du registre) est la forme attendue ; une dénomination saisie à la
     * main reste acceptée — l'étude traite aussi des sociétés qu'elle n'a pas constituées, et
     * les dossiers ouverts avant le registre n'ont pas de lien. Mais l'un des deux est
     * indispensable : sans dénomination, ni les statuts mis à jour ni le PV ne sont rédigeables.
     */
    private function verifierSocieteRattachee(Dossier $dossier): array
    {
        if ($dossier->societe_id !== null || filled($this->donnee($dossier, 'soc.denomination'))) {
            return [];
        }

        return ['modif_societe' => [
            'La société à modifier doit être désignée : choisissez-la dans le registre, ou renseignez au moins sa dénomination.',
        ]];
    }

    /**
     * Dossier constitutif d'une société que l'étude **n'a pas constituée**.
     *
     * Ne s'applique qu'à ces sociétés-là ({@see Societe::exigePiecesConstitutives()}) : pour celles
     * que l'étude a constituées, le dossier d'origine contient déjà statuts, PV, RCCM et DNSV — rien
     * à redemander.
     *
     * Les **statuts** sont bloquants parce qu'on ne peut pas produire des « statuts mis à jour »
     * sans avoir vu les statuts d'origine — ce sont même eux qui en servent de gabarit
     * ({@see \App\Services\ActesGeneratorService}). Le **RCCM** l'est parce qu'il porte l'identité
     * légale reprise dans tous les actes. Le reste (NIF, actes antérieurs, attestation de dépôt,
     * insertion, DNSV) est utile mais ne conditionne pas la rédaction.
     */
    private function verifierPiecesConstitutives(Dossier $dossier): array
    {
        $societe = $dossier->societe;

        if (!$societe || !$societe->exigePiecesConstitutives()) {
            return [];
        }

        $societe->loadMissing('piecesConstitutives.versionActuelle');
        $manquantes = $societe->piecesConstitutivesManquantes();

        if ($manquantes === []) {
            return [];
        }

        return ['modif_pieces_societe' => [sprintf(
            "Cette société n'a pas été constituée par l'étude : son dossier constitutif doit être versé au registre. Manque %s.",
            implode(', ', array_map(fn (string $l) => "« {$l} »", $manquantes)),
        )]];
    }

    /** Le procès-verbal est produit dans tous les cas : sans date d'assemblée, il n'est pas rédigeable. */
    private function verifierAssemblee(Dossier $dossier): array
    {
        if (filled($this->donnee($dossier, 'ag.date'))) {
            return [];
        }

        return ['modif_assemblee' => [
            "La date de l'assemblée qui décide la modification doit être renseignée : elle figure au procès-verbal.",
        ]];
    }

    /**
     * Cession de parts — assiette du droit de 2 % (règle 10), et cohérence des parts.
     *
     * L'assiette est la **valeur des parts cédées**, pas le capital social : sans elle, la
     * facture serait fausse. On contrôle aussi qu'un cédant ne cède pas plus de parts qu'il
     * n'en détient — une incohérence que les statuts mis à jour propageraient telle quelle.
     *
     * @param array<int, TypeModificationStatutaire> $types
     */
    private function verifierCession(Dossier $dossier, array $types): array
    {
        if (!$this->contient($types, TypeModificationStatutaire::CapitalCession)) {
            return [];
        }

        $erreurs = [];

        if ((int) $this->donnee($dossier, 'modif.valeur_parts_cedees') <= 0) {
            $erreurs['modif_valeur_parts'] = [
                "La valeur des parts cédées doit être renseignée : elle sert d'assiette au droit de cession de 2 %.",
            ];
        }

        $cedants       = $this->donnee($dossier, 'modif.cedants') ?? [];
        $cessionnaires = $this->donnee($dossier, 'modif.cessionnaires') ?? [];

        if (!$this->auMoinsUnNomme($cedants) || !$this->auMoinsUnNomme($cessionnaires)) {
            $erreurs['modif_parties_cession'] = [
                'Une cession de parts exige au moins un cédant et un cessionnaire identifiés.',
            ];
        }

        $excedents = [];
        foreach (is_array($cedants) ? $cedants : [] as $cedant) {
            if (!is_array($cedant)) {
                continue;
            }
            $detenues = (int) ($cedant['parts_detenues'] ?? 0);
            $cedees   = (int) ($cedant['parts_cedees'] ?? 0);
            // `parts_detenues` est facultatif : on ne compare que si l'information existe,
            // pour ne pas bloquer sur une supposition.
            if ($detenues > 0 && $cedees > $detenues) {
                $excedents[] = sprintf('%s (%d cédées sur %d détenues)', $cedant['nom'] ?? '—', $cedees, $detenues);
            }
        }

        if ($excedents) {
            $erreurs['modif_parts_cedees'] = [sprintf(
                'Un cédant ne peut céder plus de parts qu\'il n\'en détient : %s.',
                implode(', ', $excedents),
            )];
        }

        return $erreurs;
    }

    /**
     * Augmentation et diminution de capital.
     *
     * Le contrôle décisif est celui de la diminution : réduire le capital d'une SA sous le
     * minimum légal est illégal, et ce garde-fou existait déjà pour la **constitution**
     * (règle 1) sans jamais s'appliquer à la modification — on pouvait donc obtenir par
     * réduction ce que la constitution interdisait.
     *
     * @param array<int, TypeModificationStatutaire> $types
     */
    private function verifierCapital(Dossier $dossier, array $types): array
    {
        $erreurs = [];
        $capital = (int) $this->donnee($dossier, 'soc.capital_chiffres');

        if ($this->contient($types, TypeModificationStatutaire::CapitalAugmentation)
            && (int) $this->donnee($dossier, 'modif.augmentation_montant') <= 0) {
            $erreurs['modif_augmentation'] = [
                "Le montant de l'augmentation de capital doit être renseigné : il est constaté par la DNSV.",
            ];
        }

        if (!$this->contient($types, TypeModificationStatutaire::CapitalDiminution)) {
            return $erreurs;
        }

        $reduction = (int) $this->donnee($dossier, 'modif.diminution_montant');

        if ($reduction <= 0) {
            $erreurs['modif_diminution'] = ['Le montant de la réduction de capital doit être renseigné.'];

            return $erreurs;
        }

        $apres = $capital - $reduction;

        if ($apres <= 0) {
            $erreurs['modif_diminution'] = [sprintf(
                'La réduction (%s GNF) laisserait un capital nul ou négatif : une société ne peut exister sans capital. Une réduction totale relève de la dissolution.',
                number_format($reduction, 0, ',', ' '),
            )];

            return $erreurs;
        }

        // La forme d'un dossier de modification n'est pas dans le code du type d'acte
        // (`SOC-MOD` n'en désigne aucune) : elle vient du questionnaire, projeté depuis la
        // fiche du registre.
        $minimum = $this->forme($dossier)?->capitalMinimum();
        if ($minimum !== null && $apres < $minimum) {
            $erreurs['modif_diminution'] = [sprintf(
                'Capital après réduction insuffisant : %s GNF, alors qu\'une %s exige au moins %s GNF.',
                number_format($apres, 0, ',', ' '),
                $this->forme($dossier)->value,
                number_format($minimum, 0, ',', ' '),
            )];
        }

        return $erreurs;
    }

    /**
     * Changement de gérant — statutaire ou non.
     *
     * Le gérant entrant doit être identifié : c'est lui que le PV nomme, et lui dont les
     * pièces d'identité sont exigées. Le gérant sortant, en revanche, n'est qu'une mention —
     * un gérant décédé ne fournit plus rien.
     *
     * @param array<int, TypeModificationStatutaire> $types
     */
    private function verifierGerance(Dossier $dossier, array $types): array
    {
        $concerne = $this->contient($types, TypeModificationStatutaire::GerantStatutaire)
            || $this->contient($types, TypeModificationStatutaire::GerantNonStatutaire);

        if (!$concerne) {
            return [];
        }

        if (filled($this->donnee($dossier, 'gerant_entrant.prenom_nom'))) {
            return [];
        }

        return ['modif_gerant' => [
            'Le gérant entrant doit être identifié : il est nommé au procès-verbal et ses pièces sont exigées.',
        ]];
    }

    /**
     * Impact et documents attendus d'une modification statutaire, pour affichage dans
     * l'onglet Informations — c'est ce qui dit au clerc quelles formalités suivront.
     *
     * Restitue l'**union** des exigences des modifications décidées : un seul procès-verbal
     * pour trois résolutions, et la DNSV seulement si le capital augmente.
     */
    public function modificationStatutaire(Dossier $dossier): ?array
    {
        if ($dossier->typeActe?->code !== 'SOC-MOD') {
            return null;
        }

        $types = $this->typesModification($dossier);

        if ($types === []) {
            return null;
        }

        $requis = TypeModificationStatutaire::documentsRequisPour($types);

        return [
            'types'           => array_map(fn (TypeModificationStatutaire $t) => $t->resume(), $types),
            'impacteStatuts'  => (bool) array_filter($types, fn ($t) => $t->impacteStatuts()),
            'impacteRccm'     => (bool) array_filter($types, fn ($t) => $t->impacteRccm()),
            'exigeDnsv'       => (bool) array_filter($types, fn ($t) => $t->exigeDnsv()),
            'documentsRequis' => $requis,
            'documents'       => $this->disponibiliteDocuments($dossier, $requis),
        ];
    }

    /**
     * Pour chaque acte attendu, l'étude peut-elle réellement le produire ?
     *
     * Le panneau « Actes attendus au dossier » dérive de {@see TypeModificationStatutaire}, la
     * génération lit `modeles_actes` : **rien ne reliait les deux**. Il annonçait donc quatre
     * documents alors qu'aucun modèle n'était actif, et l'onglet Actes restait désespérément vide
     * sans explication — constaté sur `SOC-2026-0013`. Promettre un document que rien ne peut
     * produire est un mensonge d'interface.
     *
     * @param  array<string, string> $requis slug => libellé
     * @return array<int, array{slug: string, label: string, disponible: bool, source: string}>
     */
    private function disponibiliteDocuments(Dossier $dossier, array $requis): array
    {
        $modeles = ModeleActe::pourTypeActe($dossier->type_acte_id)
            ->where('est_actif', true)
            ->with('typesActes')
            ->get();

        // Les statuts mis à jour se produisent depuis les statuts en vigueur de la société, même
        // sans modèle actif — c'est même la source préférée (voir ActesGeneratorService).
        $statutsHerites = $dossier->societe?->gabaritStatutsEnVigueur();

        return collect($requis)->map(function (string $label, string $slug) use ($modeles, $statutsHerites) {
            $modele = $modeles->firstWhere('type_document', $slug);

            if ($slug === 'statuts_maj' && $statutsHerites) {
                return ['slug' => $slug, 'label' => $label, 'disponible' => true, 'source' => $statutsHerites['origine']];
            }

            return [
                'slug'       => $slug,
                'label'      => $label,
                'disponible' => (bool) $modele,
                'source'     => $modele ? 'modèle « ' . $modele->nom . ' »' : '',
            ];
        })->values()->all();
    }

    /**
     * Types de modification du dossier, avec repli sur l'ancien champ unique.
     *
     * La migration du 2026-08-11 convertit `modif.type` en `modif.types`, mais un brouillon
     * en cours de saisie ou un import peut encore porter l'ancienne forme : la refuser
     * bloquerait le dossier sans recours.
     *
     * @return array<int, TypeModificationStatutaire>
     */
    private function typesModification(Dossier $dossier): array
    {
        // Passe par le registre pour que la liste des clés lues reste déclarée à un seul endroit
        // (`TypeModificationStatutaire::clesQuestionnaire()`). Le filtre d'instance n'est pas une
        // précaution de style : `duDossier()` rend les variantes du type d'acte du dossier, qui
        // peuvent être des VarianteDissolution — les passer à `contient()` provoquerait une
        // comparaison toujours fausse, en silence.
        return array_values(array_filter(
            \App\Support\VariantesTypeActe::duDossier($dossier),
            fn ($v) => $v instanceof TypeModificationStatutaire,
        ));
    }

    /** @param array<int, TypeModificationStatutaire> $types */
    private function contient(array $types, TypeModificationStatutaire $cherche): bool
    {
        return in_array($cherche, $types, true);
    }

    /** Un bloc répétable comporte-t-il au moins une entrée nommée ? */
    private function auMoinsUnNomme(mixed $items): bool
    {
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item) && filled($item['nom'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Règles applicables telles que l'application les appliquera, pour restitution dans
     * l'assistant et dans Paramètres > Règles de gestion — les règles doivent guider la
     * saisie, pas se découvrir au moment du blocage.
     */
    public function reglesPourDossier(Dossier $dossier): ?array
    {
        if ($dossier->typeActe?->categorie !== CategorieActe::Societe) {
            return null;
        }

        return $this->forme($dossier)?->reglesApplicables();
    }

    /**
     * Forme juridique du dossier.
     *
     * Le **code du type d'acte est prioritaire** sur le questionnaire : `soc.forme`
     * n'existe que dans un seul des questionnaires de société, et les 10 dossiers réels
     * en sont tous dépourvus. Le type d'acte, lui, est toujours choisi dans l'assistant.
     * Le questionnaire ne sert que de repli, pour un type d'acte non reconnu.
     */
    private function forme(Dossier $dossier): ?FormeSociete
    {
        $dossier->loadMissing('typeActe');

        $depuisType = FormeSociete::depuisCodeTypeActe($dossier->typeActe?->code);
        if ($depuisType) {
            return $depuisType;
        }

        $valeur = $this->donnee($dossier, 'soc.forme');

        return $valeur ? FormeSociete::tryFrom(trim((string) $valeur)) : null;
    }

    /** Associés du dossier, quel que soit leur rôle exact (associé ou associé unique). */
    private function associes(Dossier $dossier)
    {
        $dossier->loadMissing('parties.client');

        return $dossier->parties->whereIn('role', ['associe', 'associe_unique'])->values();
    }

    private function donnee(Dossier $dossier, string $cle): mixed
    {
        $dossier->loadMissing('questionnaire');

        return $dossier->questionnaire?->donnees[$cle] ?? null;
    }
}
