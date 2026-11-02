<?php

namespace App\Services;

use App\Contracts\EffetSurLaFicheSociete;
use Carbon\Carbon;
use App\Enums\TypeModificationStatutaire;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\SocieteAssocie;
use App\Models\SocieteGerant;
use App\Models\SocieteHistorique;
use App\Models\Societe;
use App\Models\User;
use App\Support\Normalisation;

/**
 * Applique à la fiche du registre les modifications qu'un dossier `SOC-MOD` a décidées.
 *
 * Sans ce service, le registre se figerait sur l'état de constitution : un dossier de
 * transfert de siège aboutirait, les actes seraient signés et enregistrés, et la fiche
 * continuerait d'afficher l'ancien siège — donc le dossier de modification **suivant** se
 * préremplirait avec une adresse périmée. C'est précisément la régression que le registre
 * était censé éviter.
 *
 * **Quand** : à l'entrée du dossier en étape **Expédition** (voir
 * {@see DossierStepService::avancer()}). Ni avant ni après, et le choix se défend :
 *   - avant les Formalités, la modification n'est pas opposable — inscrire au registre un
 *     changement non enregistré au RCCM serait faux ;
 *   - sortir des Formalités signifie que tous les retours sont reçus, donc que le greffe et
 *     la DGI ont enregistré ;
 *   - à la Clôture, ce serait trop tard : le dossier y est figé, et un dossier peut rester
 *     des semaines en Expédition pendant lesquelles la fiche mentirait.
 *
 * **Traçabilité** : un `JournalActivite` par application, détaillant chaque champ modifié.
 * Une fiche de référence notariale qui change en silence est inacceptable — l'équipe doit
 * pouvoir constater, depuis le dossier, ce qui a été porté au registre.
 */
class SocieteMutationService implements EffetSurLaFicheSociete
{
    public function nom(): string
    {
        return 'modification statutaire';
    }

    /**
     * Garde d'application — reprise **mot pour mot** de celle qui ouvrait `appliquer()`.
     *
     * Le comportement est donc strictement identique à celui d'avant l'extraction du contrat
     * {@see EffetSurLaFicheSociete} (2026-09-28), et un test le vérifie plutôt que de le
     * supposer. La garde reste également en tête d'`appliquer()` : ce service est appelé
     * directement par des tests existants, et une méthode qui ne se protège que par la
     * politesse de son appelant est une invitation.
     */
    public function concerne(Dossier $dossier): bool
    {
        $dossier->loadMissing('typeActe', 'questionnaire', 'societe');

        return $dossier->typeActe?->code === 'SOC-MOD' && (bool) $dossier->societe;
    }

    /**
     * **Pas de retour arrière**, volontairement — un avertissement journalisé à la place.
     *
     * L'asymétrie avec {@see SocieteCycleVieService::annuler()} est assumée. Là-bas, l'état
     * précédent se déduit de la phase : le revert est sûr. Ici, il faudrait reconstituer
     * l'ancien siège, l'ancien capital ou l'ancien gérant depuis le questionnaire — or une
     * correction a pu être portée au registre entre-temps, et la réécrire l'effacerait sans
     * trace. Un défaut bruyant vaut mieux qu'un revert hasardeux : l'équipe est prévenue que
     * la fiche porte encore une modification dont le dossier est reparti en correction, et
     * décide elle-même.
     *
     * @return array<string, array{avant: mixed, apres: mixed}>
     */
    public function annuler(Dossier $dossier, ?User $user = null): array
    {
        if (! $this->concerne($dossier)) {
            return [];
        }

        JournalActivite::enregistrer(
            $dossier,
            sprintf(
                'Dossier renvoyé en correction : la fiche société « %s » conserve les modifications déjà portées au registre. '
                . 'Elles ne sont pas défaites automatiquement — une correction faite depuis serait écrasée. Vérifiez la fiche.',
                $dossier->societe->denomination,
            ),
            'societe',
            [],
            $user,
        );

        return [];
    }

    /**
     * @return array<string, array{avant: mixed, apres: mixed}> Champs effectivement modifiés
     */
    public function appliquer(Dossier $dossier, ?User $user = null): array
    {
        $dossier->loadMissing('typeActe', 'questionnaire', 'societe');

        if ($dossier->typeActe?->code !== 'SOC-MOD' || !$dossier->societe) {
            return [];
        }

        $donnees = $dossier->questionnaire?->donnees ?? [];
        // Le registre lit les clés déclarées par l'enum, ici `modif.types` puis `modif.type`.
        // Le filtre d'instance est redondant avec la garde `SOC-MOD` ci-dessus, et volontaire :
        // il rend l'hypothèse vérifiable plutôt que supposée.
        $types   = array_values(array_filter(
            \App\Support\VariantesTypeActe::duDossier($dossier),
            fn ($v) => $v instanceof TypeModificationStatutaire,
        ));

        if ($types === []) {
            return [];
        }

        $societe = $dossier->societe;

        // ⚠️ **L'instantané se prend avant toute écriture.** Le relire après aurait rendu
        // l'historique inutile : `etat_avant` aurait valu `etat_apres`.
        $etats = app(EtatSocieteService::class);
        $avantComplet = $etats->courant($societe);

        [$nouveau, $colonnesEnConflit] = $this->fusionnerChangements($types, $donnees, $societe);

        // Ne retenir que les champs dont la valeur change réellement : sans ce filtre, le
        // journal annoncerait des modifications là où le questionnaire ne fait que recopier
        // l'état existant (cas fréquent — les champs `soc.*` sont projetés depuis la fiche).
        $modifications = [];
        foreach ($nouveau as $colonne => $valeur) {
            if (blank($valeur)) {
                continue;
            }
            $avant = $societe->{$colonne};
            if ($this->identiques($avant, $valeur)) {
                continue;
            }
            $modifications[$colonne] = ['avant' => $avant, 'apres' => $valeur];
        }

        // Le refus est journalisé même si rien d'autre ne change : une colonne écartée doit laisser
        // une trace, sinon l'équipe croirait la fiche à jour.
        if ($colonnesEnConflit !== []) {
            $this->journaliserRefus($dossier, $societe, $colonnesEnConflit, $user);
        }

        // ⚠️ **Plus de retour anticipé ici.** Une cession de parts ne change **aucune colonne**
        // de `societes` — elle déplace des lignes dans `societe_associes`. Sortir sur
        // « aucune colonne modifiée » faisait exactement ce que le dépôt reprochait à
        // `changementsPour(CapitalCession)` : ne rien porter au registre.
        if ($modifications !== []) {
            $societe->update([
                ...array_map(fn (array $d) => $d['apres'], $modifications),
                'derniere_modification_at' => now(),
            ]);

            JournalActivite::enregistrer(
                $dossier,
                sprintf(
                    'Fiche société « %s » mise à jour au registre : %s',
                    $societe->denomination,
                    implode(', ', array_map(
                        fn (string $c) => self::LIBELLES_CHAMPS[$c] ?? $c,
                        array_keys($modifications),
                    )),
                ),
                'modification',
                ['societe_id' => $societe->id, 'champs' => $modifications],
                $user,
            );
        }

        // La composition — associés et gérance — vit dans ses propres tables, pas dans des
        // colonnes. Elle s'applique donc après, et indépendamment de `$modifications`.
        $this->appliquerComposition($dossier, $societe, $types, $donnees, $etats);

        $this->poserHistorique($dossier, $societe->fresh(), $types, $donnees, $avantComplet, $etats, $user);

        return $modifications;
    }

    /**
     * Porte la nouvelle répartition du capital et la gérance au registre.
     *
     * **C'est ce qui manquait.** `changementsPour(CapitalCession)` rendait un tableau vide, avec
     * ce commentaire : « le registre ne tient pas de table des associés ». Il en tient une
     * depuis l'addendum v1.2, et une cession y déplace enfin des parts.
     *
     * ⚠️ **Fermer avant d'ouvrir.** Un associé qui sort voit sa ligne datée (`date_sortie`), il
     * n'est pas supprimé : l'historique de détention se lit dans la table. Un associé dont les
     * parts changent voit sa ligne fermée et une nouvelle ouverte — sans quoi « qui détenait
     * quoi en mars 2027 » n'aurait plus de réponse.
     *
     * @param array<int, TypeModificationStatutaire> $types
     */
    private function appliquerComposition(
        Dossier $dossier,
        Societe $societe,
        array $types,
        array $donnees,
        EtatSocieteService $etats,
    ): void {
        $codes = array_map(fn (TypeModificationStatutaire $t) => $t->value, $types);
        $toucheRepartition = array_intersect($codes, [
            TypeModificationStatutaire::CapitalCession->value,
            TypeModificationStatutaire::CapitalAugmentation->value,
            TypeModificationStatutaire::CapitalDiminution->value,
        ]) !== [];

        $toucheGerance = array_intersect($codes, [
            TypeModificationStatutaire::GerantStatutaire->value,
            TypeModificationStatutaire::GerantNonStatutaire->value,
        ]) !== [];

        if (! $toucheRepartition && ! $toucheGerance) {
            return;
        }

        $projete = $etats->projete($societe, $types, $donnees);
        $effet   = $this->dateEffet($dossier, $donnees);

        if ($toucheRepartition) {
            $this->appliquerRepartition($societe, $dossier, $projete['associes'], $effet);
        }

        if ($toucheGerance) {
            $this->appliquerGerance($societe, $dossier, $projete['gerants'], $donnees, $effet);
        }
    }

    /**
     * Porte au registre la répartition projetée.
     *
     * ⚠️ **L'appariement ne se fait plus par le nom seul.** Il passait par `keyBy('nom')` des
     * deux côtés : une variation de casse, un accent ou une espace en trop entre l'état du
     * registre et ce que le questionnaire a enregistré faisait **sortir** l'associé réel et
     * **entrer** un homonyme fantôme, avec ses parts. Rien ne le signalait — la somme restait
     * juste, et seul le nom changeait dans la fiche.
     *
     * La clé est désormais la **fiche client** quand elle existe, et le nom normalisé sinon.
     * Un associé entrant n'a pas encore de fiche au registre : il est donc légitimement
     * apparié par son nom, et c'est le seul cas où cela reste nécessaire.
     *
     * @param array<int, array<string, mixed>> $voulus
     */
    private function appliquerRepartition(Societe $societe, Dossier $dossier, array $voulus, string $effet): void
    {
        $actuels = $societe->associesActuels()->get()
            ->keyBy(fn (SocieteAssocie $a) => $this->cleAssocie($a->client_id, $a->nom));

        $cibles = collect($voulus)
            ->keyBy(fn (array $c) => $this->cleAssocie($c['client_id'] ?? null, $c['nom']));

        foreach ($actuels as $cle => $ligne) {
            $cible = $cibles->get($cle);

            // Inchangé : on ne touche à rien. Sans ce filtre, rejouer l'application
            // fermerait puis rouvrirait chaque ligne, et l'historique de détention
            // deviendrait illisible.
            if ($cible && (int) $cible['nb_parts'] === (int) $ligne->nb_parts) {
                continue;
            }

            $ligne->update(['date_sortie' => $effet, 'dossier_sortie_id' => $dossier->id]);
        }

        foreach ($cibles as $cle => $cible) {
            $actuel = $actuels->get($cle);

            if ($actuel && (int) $actuel->nb_parts === (int) $cible['nb_parts']) {
                continue;
            }

            SocieteAssocie::create([
                'societe_id' => $societe->id,
                // La fiche vient de la projection, qui la conserve depuis le registre
                // ({@see EtatSocieteService::repartition()}). Elle reste nulle pour un
                // entrant, dont la fiche n'est pas encore au registre.
                'client_id'         => $cible['client_id'] ?? null,
                'nom'               => $cible['nom'],
                'nb_parts'          => (int) $cible['nb_parts'],
                'date_entree'       => $effet,
                'dossier_entree_id' => $dossier->id,
            ]);
        }
    }

    /**
     * La clé d'appariement d'un associé : sa fiche, ou son nom normalisé.
     *
     * Normaliser, c'est replier sur la même clé « Ibrahima DIALLO », « ibrahima diallo » et
     * « Ibrahima  Diallo » — trois écritures qu'un questionnaire produit couramment et que
     * l'ancienne comparaison stricte traitait comme trois personnes.
     *
     * ⚠️ Le préfixe `c:` / `n:` empêche un identifiant de fiche de se confondre avec un nom
     * qui serait un nombre. Improbable, gratuit à écarter.
     */
    private function cleAssocie(?int $clientId, ?string $nom): string
    {
        if ($clientId !== null) {
            return 'c:' . $clientId;
        }

        // ⚠️ `Normalisation::comparable()` et non une normalisation maison : c'est le détenteur
        // unique de cette comparaison dans le dépôt (dénomination de société, référentiel de
        // lieux), et il retire **aussi les accents** — « Aissatou » et « Aïssatou » sont la
        // même personne tapée deux fois. Une première version ne traitait que la casse et les
        // espaces, et laissait donc passer le cas le plus fréquent.
        return 'n:' . Normalisation::comparable($nom);
    }

    /** @param array<int, array<string, mixed>> $voulus */
    private function appliquerGerance(Societe $societe, Dossier $dossier, array $voulus, array $donnees, string $effet): void
    {
        $sortant = $donnees['gerant_sortant.prenom_nom'] ?? null;

        if (filled($sortant)) {
            // ⚠️ **La comparaison se faisait en SQL, à la chaîne exacte** (`where('nom', …)`).
            // Un gérant sortant dont le nom était tapé avec une majuscule ou un accent de
            // différence n'était tout simplement **pas clôturé** — et comme le nouveau était
            // créé juste après, la société se retrouvait avec deux gérants en fonction. Rien
            // ne le signalait : les deux lignes étaient valides.
            //
            // Le filtrage passe donc en PHP, sur la forme comparable. Le coût est nul : une
            // société compte une poignée de gérants.
            $cibleSortant = Normalisation::comparable($sortant);

            $societe->gerantsEnFonction()->get()
                ->filter(fn (SocieteGerant $g) => Normalisation::comparable($g->nom) === $cibleSortant)
                ->each(fn (SocieteGerant $g) => $g->update([
                    'date_fin'       => $effet,
                    'motif_fin'      => $donnees['gerant_sortant.motif_fin'] ?? null,
                    'dossier_fin_id' => $dossier->id,
                ]));
        }

        // Même raison en sens inverse : un gérant déjà en fonction sous une orthographe
        // voisine était considéré comme absent, et donc **dupliqué**.
        $enFonction = $societe->gerantsEnFonction()->pluck('nom')
            ->map(fn (string $nom) => Normalisation::comparable($nom))
            ->all();

        foreach ($voulus as $gerant) {
            if (in_array(Normalisation::comparable($gerant['nom']), $enFonction, true)) {
                continue;
            }

            SocieteGerant::create([
                'societe_id'            => $societe->id,
                'client_id'             => $gerant['client_id'] ?? null,
                'nom'                   => $gerant['nom'],
                'statutaire'            => (bool) ($gerant['statutaire'] ?? true),
                'date_nomination'       => $effet,
                'dossier_nomination_id' => $dossier->id,
            ]);
        }
    }

    /**
     * Pose la ligne d'historique — ou n'en pose pas une seconde.
     *
     * ⚠️ **Idempotence (cas T12 de la spec)** : rejouer un avancement après un recul ne doit pas
     * doubler l'histoire. La garde est portée par l'unicité `(societe_id, dossier_id)` en base
     * **et** vérifiée ici, parce qu'une exception d'intégrité au milieu d'un avancement de
     * dossier serait un mauvais message.
     *
     * @param array<int, TypeModificationStatutaire> $types
     */
    private function poserHistorique(
        Dossier $dossier,
        Societe $societe,
        array $types,
        array $donnees,
        array $avant,
        EtatSocieteService $etats,
        ?User $user,
    ): void {
        if (SocieteHistorique::where('societe_id', $societe->id)->where('dossier_id', $dossier->id)->exists()) {
            return;
        }

        $apres = $etats->courant($societe);

        // Rien n'a bougé : une ligne d'historique sans différence encombrerait la timeline
        // sans rien apprendre.
        if ($avant == $apres) {
            return;
        }

        SocieteHistorique::create([
            'societe_id'    => $societe->id,
            'dossier_id'    => $dossier->id,
            'operations'    => array_map(fn (TypeModificationStatutaire $t) => $t->value, $types),
            'date_effet'    => $this->dateEffet($dossier, $donnees),
            'etat_avant'    => $avant,
            'etat_apres'    => $apres,
            'appliquee_le'  => now(),
            'appliquee_par' => $user?->id,
        ]);
    }

    /**
     * La première ligne d'histoire d'une société : « voici l'état d'où l'on part ».
     *
     * Addendum v1.2, cas T5 — une société que l'étude n'a pas constituée doit voir son
     * historique commencer à l'état initialisé, sans quoi sa timeline débuterait à sa première
     * modification comme si rien n'avait précédé. `etat_avant` est nul : c'est ce qui distingue
     * cette ligne de toutes les suivantes.
     *
     * ⚠️ **Deux appelants, une seule écriture.** `ayelema:backfill-associes` la pose pour les
     * 15 fiches déjà au registre, {@see \App\Http\Controllers\SocieteController::store()} pour
     * chaque société externe créée depuis l'assistant. La même ligne écrite à deux endroits
     * aurait divergé — c'est la famille de défauts que le dépôt a déjà payée sur les dates et
     * sur les moteurs de rendu.
     *
     * Idempotente : la garde porte sur l'absence de ligne **sans dossier**, puisque c'est la
     * forme d'une ligne initiale. Rend `true` si elle a écrit.
     */
    public function poserEtatInitial(
        Societe $societe,
        EtatSocieteService $etats,
        ?string $dateEffet = null,
        ?int $parUtilisateur = null,
    ): bool {
        if (SocieteHistorique::where('societe_id', $societe->id)->whereNull('dossier_id')->exists()) {
            return false;
        }

        SocieteHistorique::create([
            'societe_id'    => $societe->id,
            'dossier_id'    => null,
            'operations'    => ['etat_initial'],
            'date_effet'    => $dateEffet
                ?? $societe->date_constitution?->toDateString()
                ?? now()->toDateString(),
            'etat_avant'    => null,
            'etat_apres'    => $etats->courant($societe->fresh()),
            'appliquee_le'  => now(),
            'appliquee_par' => $parUtilisateur,
        ]);

        return true;
    }

    /**
     * La date à laquelle l'opération prend effet.
     *
     * Celle de l'**assemblée**, pas celle du traitement : les deux diffèrent de semaines, et
     * c'est la première que l'acte cite. Même choix que `SocieteCycleVieService`, qui lit la
     * date d'assemblée de la dissolution plutôt que `now()`.
     */
    private function dateEffet(Dossier $dossier, array $donnees): string
    {
        foreach (['ag.date_effet', 'ag.date'] as $cle) {
            $valeur = $donnees[$cle] ?? null;

            if (filled($valeur)) {
                try {
                    return Carbon::createFromFormat('d/m/Y', (string) $valeur)->toDateString();
                } catch (\Throwable) {
                    // Une date illisible ne doit pas empêcher d'appliquer l'opération : on
                    // retombe sur aujourd'hui, et le questionnaire la signale par ailleurs.
                }
            }
        }

        return now()->toDateString();
    }

    /**
     * Fusionne les changements apportés par chaque type, en **détectant les collisions** plutôt
     * qu'en laissant le dernier écraser le précédent.
     *
     * Défense en profondeur : `ReglesSocieteService` refuse déjà d'avancer un dossier portant deux
     * modifications incompatibles, et l'assistant grise les cases exclues. Mais un dossier ouvert
     * avant ces garde-fous peut déjà être en cours, et la version précédente de cette méthode
     * retenait alors « la dernière valeur fusionnée » — c'est-à-dire que **l'ordre de déclaration
     * de l'enum décidait du capital de la société**, en silence.
     *
     * Une colonne réclamée avec deux valeurs différentes est **écartée**, pas arbitrée. Les autres
     * s'appliquent normalement : un conflit sur le capital ne doit pas empêcher d'enregistrer le
     * transfert de siège décidé par la même assemblée.
     *
     * Deux types proposant la **même** valeur ne sont pas en conflit — cas des deux changements de
     * gérant, qui écrivent le même bloc `direction`.
     *
     * @param  array<int, TypeModificationStatutaire> $types
     * @return array{0: array<string, mixed>, 1: array<int, string>} [valeurs retenues, colonnes écartées]
     */
    private function fusionnerChangements(array $types, array $donnees, Societe $societe): array
    {
        $retenues = [];
        $conflits = [];

        foreach ($types as $type) {
            foreach ($this->changementsPour($type, $donnees, $societe) as $colonne => $valeur) {
                if (blank($valeur)) {
                    continue;
                }

                if (array_key_exists($colonne, $retenues) && !$this->identiques($retenues[$colonne], $valeur)) {
                    $conflits[$colonne] = true;
                    continue;
                }

                $retenues[$colonne] = $valeur;
            }
        }

        // Une colonne en conflit ne doit surtout pas garder la première valeur rencontrée : ce
        // serait le même arbitraire, déplacé du dernier vers le premier.
        foreach (array_keys($conflits) as $colonne) {
            unset($retenues[$colonne]);
        }

        return [$retenues, array_keys($conflits)];
    }

    /**
     * Trace le refus d'appliquer une colonne — une fiche de référence notariale qui reste inchangée
     * sans explication est aussi problématique qu'une fiche modifiée en silence.
     *
     * @param array<int, string> $colonnes
     */
    private function journaliserRefus(Dossier $dossier, Societe $societe, array $colonnes, ?User $user): void
    {
        JournalActivite::enregistrer(
            $dossier,
            sprintf(
                'Fiche société « %s » — %s non porté(s) au registre : deux modifications décidées en donnent des valeurs contradictoires. À arbitrer avant clôture.',
                $societe->denomination,
                implode(', ', array_map(fn (string $c) => self::LIBELLES_CHAMPS[$c] ?? $c, $colonnes)),
            ),
            'modification',
            ['societe_id' => $societe->id, 'champs_ecartes' => $colonnes],
            $user,
        );
    }

    /** Libellés métier des colonnes, pour un journal lisible par l'équipe et non par un développeur. */
    private const LIBELLES_CHAMPS = [
        'denomination'             => 'dénomination sociale',
        'sigle'                    => 'sigle',
        'forme'                    => 'forme juridique',
        'siege_quartier'           => 'quartier du siège',
        'siege_commune'            => 'commune du siège',
        'siege_ville'              => 'ville du siège',
        'capital_chiffres'         => 'capital social',
        'nombre_parts'             => 'nombre de parts',
        'valeur_nominale_chiffres' => 'valeur nominale',
        'objet_social'             => 'objet social',
        'direction'                => 'gérance',
    ];

    /**
     * Nouvelles valeurs de fiche apportées par un type de modification.
     *
     * Un `match` exhaustif : ajouter un cas à l'enum sans décider de son effet sur le registre
     * fera échouer PHP, plutôt que de laisser la fiche se désynchroniser en silence.
     *
     * @return array<string, mixed>
     */
    private function changementsPour(TypeModificationStatutaire $type, array $donnees, Societe $societe): array
    {
        return match ($type) {
            TypeModificationStatutaire::DenominationSociale => array_filter([
                'denomination' => $donnees['modif.denomination_nouvelle'] ?? null,
                'sigle'        => $donnees['modif.sigle_nouveau'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),

            TypeModificationStatutaire::FormeJuridique => array_filter([
                'forme' => $donnees['modif.forme_nouvelle'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),

            TypeModificationStatutaire::DecisionGestion => [],

            TypeModificationStatutaire::SiegeSocial => [
                'siege_quartier' => $donnees['modif.siege_nouveau_quartier'] ?? null,
                'siege_commune'  => $donnees['modif.siege_nouveau_commune'] ?? null,
                'siege_ville'    => $donnees['modif.siege_nouveau_ville'] ?? null,
            ],

            TypeModificationStatutaire::CapitalAugmentation => [
                'capital_chiffres' => $donnees['modif.augmentation_capital_apres'] ?? null,
                'nombre_parts'     => $this->partsApres(
                    $societe->nombre_parts,
                    $donnees['modif.augmentation_parts_nouvelles'] ?? null,
                    1,
                ),
            ],

            TypeModificationStatutaire::CapitalDiminution => [
                'capital_chiffres' => $donnees['modif.diminution_capital_apres'] ?? null,
                'nombre_parts'     => $this->partsApres(
                    $societe->nombre_parts,
                    $donnees['modif.diminution_parts_annulees'] ?? null,
                    -1,
                ),
            ],

            TypeModificationStatutaire::ObjetSocial => [
                'objet_social' => $donnees['modif.objet_nouveau'] ?? null,
            ],

            // Statutaire ou non, le gérant change au registre : la distinction porte sur les
            // statuts (impacteStatuts()), pas sur l'identité du dirigeant en exercice.
            TypeModificationStatutaire::GerantStatutaire,
            TypeModificationStatutaire::GerantNonStatutaire => [
                'direction' => $this->direction($donnees, $societe),
            ],

            // Une cession de parts change les **associés**, pas les caractéristiques de la
            // société : ni son capital, ni son siège, ni son objet. La nouvelle répartition
            // vit dans le questionnaire du dossier et dans les statuts mis à jour ; le
            // registre ne tient pas de table des associés, et lui en inventer une ici
            // dupliquerait ce que `Partie` porte déjà.
            TypeModificationStatutaire::CapitalCession => [],
        };
    }

    /**
     * Nombre de parts après émission ou annulation.
     *
     * `null` si l'une des deux valeurs manque : le nombre de parts est facultatif au
     * questionnaire, et écrire un total faux serait pire que ne rien écrire.
     */
    private function partsApres(?int $actuelles, mixed $delta, int $signe): ?int
    {
        if ($actuelles === null || !is_numeric($delta)) {
            return null;
        }

        $apres = $actuelles + $signe * (int) $delta;

        return $apres > 0 ? $apres : null;
    }

    /**
     * Bloc `direction` (JSON) après changement de gérant, en conservant les clés déjà
     * présentes — la fiche peut porter d'autres dirigeants (président, DG) qu'un changement
     * de gérance ne concerne pas.
     */
    private function direction(array $donnees, Societe $societe): ?array
    {
        $nom = $donnees['gerant_entrant.prenom_nom'] ?? null;

        if (blank($nom)) {
            return null;
        }

        return [
            ...($societe->direction ?? []),
            'gerant'          => $nom,
            'gerant_civilite' => $donnees['gerant_entrant.civilite'] ?? null,
            'gerant_mandat'   => $donnees['gerant_entrant.duree_mandat'] ?? null,
        ];
    }

    /**
     * Comparaison tolérante aux types : `capital_chiffres` est casté en `decimal:2` et revient
     * en « 50000000.00 », alors que le questionnaire fournit la chaîne « 50000000 ». Sans
     * cela, chaque passage en Expédition signalerait une modification du capital.
     */
    private function identiques(mixed $avant, mixed $apres): bool
    {
        if (is_array($avant) || is_array($apres)) {
            return $avant == $apres;
        }

        if (is_numeric($avant) && is_numeric($apres)) {
            return (float) $avant === (float) $apres;
        }

        return trim((string) $avant) === trim((string) $apres);
    }
}
