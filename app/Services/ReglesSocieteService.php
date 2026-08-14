<?php

namespace App\Services;

use App\Enums\CategorieActe;
use App\Enums\FormeSociete;
use App\Enums\TypeModificationStatutaire;
use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\Societe;

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
     * Règle 4 — « pas de répétition de nom de société, la dénomination doit être UNIQUE ».
     *
     * La comparaison se fait sur les **questionnaires** : la table `societes` est vide, les
     * dénominations ne vivent aujourd'hui que dans `questionnaires.donnees`. Le filtrage
     * s'effectue en PHP plutôt qu'en SQL parce que la clé `soc.denomination` contient un
     * point, que la notation `donnees->soc.denomination` de Laravel interpréterait comme un
     * chemin imbriqué. Volume attendu de quelques centaines de dossiers — à revoir en
     * requête JSON native si l'étude en compte des dizaines de milliers.
     *
     * ⚠️ **Seuls les dossiers de constitution sont comparés** (correctif du 2026-08-11). Un
     * dossier de modification ou de dissolution porte la dénomination de la société qu'il
     * traite : c'est la **même** société, pas un homonyme. La version précédente comparait
     * tous les questionnaires, si bien qu'ouvrir une modification sur une société faisait
     * apparaître son propre dossier de constitution comme un doublon — constaté sur la base
     * réelle : `SOC-2026-0010` (constitution de MICH SARL) était signalé en conflit avec
     * `SOC-2026-0012`, la modification de cette même société.
     */
    private function verifierDenominationUnique(Dossier $dossier): array
    {
        $denomination = $this->normaliser($this->donnee($dossier, 'soc.denomination'));
        if ($denomination === '') {
            return [];
        }

        $conflits = Questionnaire::query()
            ->whereNot('dossier_id', $dossier->id)
            // `type_acte_id` doit figurer dans la sélection : sans la clé étrangère, Eloquent
            // ne peut pas résoudre `dossier.typeActe` et la relation revient à null — le
            // filtre ci-dessous écarterait alors *tous* les dossiers, y compris les vrais
            // homonymes, et désarmerait la règle 4 en silence.
            ->with('dossier:id,reference,type_acte_id', 'dossier.typeActe:id,code')
            ->get()
            ->filter(fn (Questionnaire $q) => $this->normaliser($q->donnees['soc.denomination'] ?? null) === $denomination)
            // `depuisCodeTypeActe()` ne retourne une forme que pour une constitution : c'est
            // exactement le critère « ce dossier crée-t-il une société ? ».
            ->filter(fn (Questionnaire $q) => FormeSociete::depuisCodeTypeActe($q->dossier?->typeActe?->code) !== null)
            ->map(fn (Questionnaire $q) => $q->dossier?->reference)
            ->filter()
            ->values();

        if ($conflits->isEmpty()) {
            return [];
        }

        return ['soc_denomination' => [sprintf(
            'Cette dénomination est déjà utilisée par le dossier %s. La dénomination sociale doit être unique.',
            $conflits->join(', '),
        )]];
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

            // Société de capitaux : toléré si la représentation est renseignée.
            if (blank($client->representant_legal) || blank($client->representant_qualite)) {
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
                'Associé mineur sans représentant légal : %s. Renseignez le tuteur et sa qualité sur la fiche client.',
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
        return TypeModificationStatutaire::depuisLibelles(
            $this->donnee($dossier, 'modif.types') ?? $this->donnee($dossier, 'modif.type'),
        );
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
