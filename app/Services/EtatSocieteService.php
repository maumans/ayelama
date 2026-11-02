<?php

namespace App\Services;

use App\Enums\TypeModificationStatutaire;
use App\Models\Societe;
use App\Models\SocieteAssocie;
use App\Support\Normalisation;

/**
 * L'état d'une société — celui du registre, et celui qu'une opération produirait.
 *
 * Addendum v1.2 § 6.2 (« répartition projetée ») et § 6.4 (« application à la société »).
 *
 * **Un seul calcul, trois consommateurs** : le tableau « Nouvelle répartition » de l'écran, les
 * balises `{projete.*}` des gabarits, et l'écriture au registre. Les séparer aurait laissé
 * l'acte dire une chose et le registre en enregistrer une autre — c'est la famille de défauts
 * que le dépôt a déjà payée trois fois sur les moteurs de rendu de questionnaire.
 *
 * ⚠️ **Le service ne garde rien.** `projete()` calcule sans écrire ; c'est
 * {@see SocieteMutationService} qui décide quand appliquer. Un service qui projette *et* écrit
 * serait appelé par prudence une fois de trop.
 */
class EtatSocieteService
{
    /**
     * L'état courant, tel que le registre le porte.
     *
     * @return array<string, mixed>
     */
    public function courant(Societe $societe): array
    {
        $societe->loadMissing(['associesActuels', 'gerantsEnFonction']);

        return [
            'denomination'      => $societe->denomination,
            'sigle'             => $societe->sigle,
            'forme'             => $societe->forme,
            'siege'             => $societe->siegeComplet(),
            'capital_chiffres'  => $societe->capital_chiffres === null ? null : (float) $societe->capital_chiffres,
            'nombre_parts'      => $societe->nombre_parts,
            'valeur_nominale'   => $this->valeurNominale($societe->capital_chiffres, $societe->nombre_parts),
            'objet_social'      => $societe->objet_social,
            'duree'             => $societe->duree,
            'rccm_numero'       => $societe->rccm_numero,
            'nif'               => $societe->nif,
            'statut'            => $societe->statut->value,
            'associes'          => $societe->associesActuels->map(fn (SocieteAssocie $a) => [
                'nom'         => $a->nom,
                'client_id'   => $a->client_id,
                'nb_parts'    => $a->nb_parts,
                'pourcentage' => $a->pourcentage((int) $societe->nombre_parts),
            ])->values()->all(),
            'gerants'           => $societe->gerantsEnFonction->map(fn ($g) => [
                'nom'        => $g->nom,
                'client_id'  => $g->client_id,
                'statutaire' => $g->statutaire,
            ])->values()->all(),
        ];
    }

    /**
     * L'état que les opérations choisies produiraient, sans rien écrire.
     *
     * ⚠️ **La cession se projette par soldes, pas par paires.** La spec décrit un tableau
     * `cessions[]` qui lie un cédant à un cessionnaire ; le questionnaire actuel porte **deux
     * listes indépendantes** — `modif.cedants` et `modif.cessionnaires`. On retranche donc à
     * chaque cédant ce qu'il cède et on ajoute à chaque cessionnaire ce qu'il acquiert, ce qui
     * donne la bonne répartition finale sans inventer un appariement que la saisie ne porte
     * pas. Le contrôle « total cédé = total acquis » remplace la vérification par paire. Les
     * paires viendront avec le nouveau formulaire (lot 4) ; les reconstituer ici aurait exigé
     * de deviner qui a vendu à qui.
     *
     * @param  array<int, TypeModificationStatutaire> $operations
     * @param  array<string, mixed> $donnees  le questionnaire du dossier
     * @return array<string, mixed>
     */
    public function projete(Societe $societe, array $operations, array $donnees): array
    {
        $etat = $this->courant($societe);
        $parts = collect($etat['associes'])->keyBy('nom')->map(fn (array $a) => $a['nb_parts'])->all();

        // Les fiches clients des associés **déjà au registre**, pour que la projection ne les
        // oublie pas en chemin. Sans elles, chaque opération recrée des associés anonymes que
        // plus rien ne relie à leur fiche — voir {@see repartition()}.
        $fiches = collect($etat['associes'])->pluck('client_id', 'nom')->all();

        foreach ($operations as $operation) {
            $etat = match ($operation) {
                TypeModificationStatutaire::DenominationSociale => [
                    ...$etat,
                    'denomination' => $donnees['modif.denomination_nouvelle'] ?? $etat['denomination'],
                    'sigle'        => $donnees['modif.sigle_nouveau'] ?? $etat['sigle'],
                ],
                TypeModificationStatutaire::FormeJuridique      => [...$etat, 'forme' => $donnees['modif.forme_nouvelle'] ?? $etat['forme']],
                TypeModificationStatutaire::ObjetSocial         => [...$etat, 'objet_social' => $donnees['modif.objet_nouveau'] ?? $etat['objet_social']],
                TypeModificationStatutaire::SiegeSocial         => [...$etat, 'siege' => $this->siegeProjete($donnees, $etat['siege'])],

                TypeModificationStatutaire::CapitalAugmentation => [
                    ...$etat,
                    'capital_chiffres' => $this->nombre($donnees['modif.augmentation_capital_apres'] ?? null) ?? $etat['capital_chiffres'],
                    'nombre_parts'     => (int) ($etat['nombre_parts'] + ($this->nombre($donnees['modif.augmentation_parts_nouvelles'] ?? null) ?? 0)),
                ],
                TypeModificationStatutaire::CapitalDiminution => [
                    ...$etat,
                    'capital_chiffres' => $this->nombre($donnees['modif.diminution_capital_apres'] ?? null) ?? $etat['capital_chiffres'],
                    'nombre_parts'     => (int) ($etat['nombre_parts'] - ($this->nombre($donnees['modif.diminution_parts_annulees'] ?? null) ?? 0)),
                ],

                // La répartition est traitée hors du `match` : cession, augmentation et
                // diminution y touchent toutes, et les enchaîner ici les ferait s'écraser.
                TypeModificationStatutaire::CapitalCession => $etat,

                // Décision de gestion en AGO : n'affecte pas l'état signalétique de la société.
                TypeModificationStatutaire::DecisionGestion => $etat,

                // La gérance ne change pas la répartition du capital.
                TypeModificationStatutaire::GerantStatutaire,
                TypeModificationStatutaire::GerantNonStatutaire => [
                    ...$etat,
                    'gerants' => $this->gerantsProjetes($etat['gerants'], $donnees, $operation),
                ],
            };
        }

        $codes = array_map(fn (TypeModificationStatutaire $o) => $o->value, $operations);

        if (in_array(TypeModificationStatutaire::CapitalCession->value, $codes, true)) {
            $parts = $this->appliquerCessions($parts, $donnees);
        }

        if (in_array(TypeModificationStatutaire::CapitalAugmentation->value, $codes, true)) {
            $parts = $this->appliquerSouscriptions($parts, $donnees);
        }

        $etat['associes'] = $this->repartition($parts, (int) $etat['nombre_parts'], $fiches);
        $etat['valeur_nominale'] = $this->valeurNominale($etat['capital_chiffres'], $etat['nombre_parts']);

        return $etat;
    }

    /**
     * Ce qui empêche d'enregistrer une répartition fausse — § 6.2, « contrôle bloquant ».
     *
     * Rendus comme des messages et non comme un booléen : le dépôt préfère un blocage énuméré
     * à un bouton grisé, et « la répartition est invalide » n'apprend rien à qui doit la
     * corriger.
     *
     * @param  array<string, mixed> $projete
     * @return array<int, string>
     */
    public function anomaliesRepartition(array $projete, array $donnees = []): array
    {
        $anomalies = [];
        $partsTotales = (int) ($projete['nombre_parts'] ?? 0);
        $reparties = array_sum(array_column($projete['associes'] ?? [], 'nb_parts'));

        if ($partsTotales > 0 && $reparties !== $partsTotales) {
            $anomalies[] = sprintf(
                'La répartition porte %s part(s) pour un capital qui en compte %s : '
                . 'l\'écart est de %s. Chaque part doit être attribuée.',
                number_format($reparties, 0, ',', ' '),
                number_format($partsTotales, 0, ',', ' '),
                number_format(abs($reparties - $partsTotales), 0, ',', ' '),
            );
        }

        foreach ($projete['associes'] ?? [] as $associe) {
            if ($associe['nb_parts'] < 0) {
                $anomalies[] = sprintf(
                    '%s céderait %s part(s) de plus qu\'il n\'en détient.',
                    $associe['nom'],
                    number_format(abs($associe['nb_parts']), 0, ',', ' '),
                );
            }
        }

        $cessions = $this->bloc($donnees, 'modif.cessions');
        if ($cessions !== []) {
            foreach ($cessions as $idx => $c) {
                $cedant = $c['cedant_nom'] ?? (is_array($c['cedant'] ?? null) ? $this->nomDe($c['cedant']) : ($c['cedant'] ?? null));
                $cessionnaire = $c['cessionnaire_nom'] ?? (is_array($c['cessionnaire'] ?? null) ? $this->nomDe($c['cessionnaire']) : ($c['cessionnaire'] ?? null));
                $nbParts = (int) ($this->nombre($c['nb_parts'] ?? $c['parts_cedees'] ?? null) ?? 0);

                if (blank($cedant)) {
                    $anomalies[] = sprintf('La cession #%d doit désigner un cédant.', $idx + 1);
                }
                if (blank($cessionnaire)) {
                    $anomalies[] = sprintf('La cession #%d doit désigner un cessionnaire.', $idx + 1);
                }
                if ($nbParts <= 0) {
                    $anomalies[] = sprintf('La cession #%d doit porter un nombre de parts strictement positif.', $idx + 1);
                }
            }
        } else {
            $cedees  = $this->sommeBloc($donnees, 'modif.cedants', 'parts_cedees');
            $acquises = $this->sommeBloc($donnees, 'modif.cessionnaires', 'parts_acquises');

            if ($cedees > 0 && $cedees !== $acquises) {
                $anomalies[] = sprintf(
                    'Les cédants cèdent %s part(s) et les cessionnaires en acquièrent %s : '
                    . 'une part cédée doit être acquise.',
                    number_format($cedees, 0, ',', ' '),
                    number_format($acquises, 0, ',', ' '),
                );
            }
        }

        return $anomalies;
    }

    // ── Détail ───────────────────────────────────────────────────────────

    /** @param array<string, int> $parts */
    private function appliquerCessions(array $parts, array $donnees): array
    {
        $cessions = $this->bloc($donnees, 'modif.cessions');
        if ($cessions !== []) {
            foreach ($cessions as $ligne) {
                $cedant = $ligne['cedant_nom'] ?? (is_array($ligne['cedant'] ?? null) ? $this->nomDe($ligne['cedant']) : ($ligne['cedant'] ?? null));
                $cessionnaire = $ligne['cessionnaire_nom'] ?? (is_array($ligne['cessionnaire'] ?? null) ? $this->nomDe($ligne['cessionnaire']) : ($ligne['cessionnaire'] ?? null));
                $nbParts = (int) ($this->nombre($ligne['nb_parts'] ?? $ligne['parts_cedees'] ?? null) ?? 0);

                if (is_string($cedant) && filled($cedant) && $nbParts > 0) {
                    $parts[trim($cedant)] ??= (int) ($this->nombre($ligne['parts_detenues'] ?? null) ?? 0);
                    $parts[trim($cedant)] -= $nbParts;
                }

                if (is_string($cessionnaire) && filled($cessionnaire) && $nbParts > 0) {
                    $parts[trim($cessionnaire)] = ($parts[trim($cessionnaire)] ?? 0) + $nbParts;
                }
            }

            return $parts;
        }

        foreach ($this->bloc($donnees, 'modif.cedants') as $ligne) {
            $nom = $this->nomDe($ligne);
            if ($nom === null) {
                continue;
            }
            // Un cédant absent du registre part de ce qu'il déclare détenir : une fiche non
            // encore backfillée ne doit pas rendre la projection négative pour autant.
            $parts[$nom] ??= (int) ($this->nombre($ligne['parts_detenues'] ?? null) ?? 0);
            $parts[$nom] -= (int) ($this->nombre($ligne['parts_cedees'] ?? null) ?? 0);
        }

        foreach ($this->bloc($donnees, 'modif.cessionnaires') as $ligne) {
            $nom = $this->nomDe($ligne);
            if ($nom === null) {
                continue;
            }
            $parts[$nom] = ($parts[$nom] ?? 0) + (int) ($this->nombre($ligne['parts_acquises'] ?? null) ?? 0);
        }

        return $parts;
    }

    /** @param array<string, int> $parts */
    private function appliquerSouscriptions(array $parts, array $donnees): array
    {
        foreach ($this->bloc($donnees, 'modif.souscripteurs') as $ligne) {
            $nom = $this->nomDe($ligne);
            if ($nom === null) {
                continue;
            }
            $parts[$nom] = ($parts[$nom] ?? 0) + (int) ($this->nombre($ligne['parts_souscrites'] ?? null) ?? 0);
        }

        return $parts;
    }

    /**
     * @param  array<string, int>      $parts
     * @param  array<string, int|null> $fiches  Le `client_id` connu de chaque associé, par nom.
     * @return array<int, array<string, mixed>>
     */
    private function repartition(array $parts, int $partsTotales, array $fiches = []): array
    {
        $lignes = [];

        foreach ($parts as $nom => $nb) {
            // Un associé tombé à zéro **sort** : il ne figure plus dans l'état projeté (§ 6.2).
            // Un nombre négatif, lui, est conservé — c'est une anomalie à montrer, pas à taire.
            if ($nb === 0) {
                continue;
            }

            $lignes[] = [
                'nom' => $nom,
                // ⚠️ **Le lien à la fiche client se perdait ici**, forcé à `null`. Conséquence
                // mesurée : `SocieteMutationService::appliquerRepartition()` recréait chaque
                // associé sans `client_id`, si bien qu'après une seule opération le registre ne
                // savait plus quelle fiche désignait quel associé — et l'appariement retombait
                // sur la comparaison de chaînes, qu'un accent ou une majuscule suffit à casser.
                //
                // La fiche d'un associé **entrant** reste inconnue à ce stade : elle n'existe que
                // dans le questionnaire, pas encore au registre. Elle se rattachera à
                // l'application, quand les parties du dossier seront lues.
                'client_id'   => $fiches[$nom] ?? null,
                'nb_parts'    => $nb,
                'pourcentage' => $partsTotales > 0 ? round($nb * 100 / $partsTotales, 2) : null,
            ];
        }

        usort($lignes, fn (array $a, array $b) => $b['nb_parts'] <=> $a['nb_parts']);

        return $lignes;
    }

    private function siegeProjete(array $donnees, ?string $actuel): ?string
    {
        $parties = array_filter([
            $donnees['modif.siege_nouveau_quartier'] ?? null,
            $donnees['modif.siege_nouveau_commune'] ?? null,
            $donnees['modif.siege_nouveau_ville'] ?? null,
        ]);

        return $parties === [] ? $actuel : implode(', ', $parties);
    }

    /** @param array<int, array<string, mixed>> $gerants */
    private function gerantsProjetes(array $gerants, array $donnees, TypeModificationStatutaire $operation): array
    {
        $entrant = $donnees['gerant_entrant.prenom_nom'] ?? null;
        $sortant = $donnees['gerant_sortant.prenom_nom'] ?? null;

        if (filled($sortant)) {
            // ⚠️ Comparaison **normalisée**, comme à l'écriture ({@see
            // SocieteMutationService::appliquerGerance()}). En strict, un gérant sortant tapé
            // avec un accent ou une majuscule de différence restait dans l'état projeté *et*
            // le nouveau s'y ajoutait : l'acte annonçait alors deux gérants là où l'étude n'en
            // nommait qu'un, et le registre finissait par le refléter.
            $cible = Normalisation::comparable($sortant);

            $gerants = array_values(array_filter(
                $gerants,
                fn (array $g) => Normalisation::comparable($g['nom']) !== $cible,
            ));
        }

        if (filled($entrant)) {
            $gerants[] = [
                'nom'        => $entrant,
                'client_id'  => null,
                'statutaire' => $operation === TypeModificationStatutaire::GerantStatutaire,
            ];
        }

        return $gerants;
    }

    /** @return array<int, array<string, mixed>> */
    private function bloc(array $donnees, string $cle): array
    {
        $valeur = $donnees[$cle] ?? null;

        return is_array($valeur) ? array_filter($valeur, 'is_array') : [];
    }

    private function sommeBloc(array $donnees, string $cle, string $champ): int
    {
        return (int) collect($this->bloc($donnees, $cle))
            ->sum(fn (array $l) => (int) ($this->nombre($l[$champ] ?? null) ?? 0));
    }

    /**
     * Le nom d'une ligne de bloc répétable.
     *
     * `nom` et `prenom_nom` cohabitent selon les schémas — `schemaPersonne()` pose `nom`, les
     * blocs plus anciens `prenom_nom`. Lire les deux évite qu'une cession saisie dans un bloc
     * d'une génération soit ignorée par la projection.
     */
    private function nomDe(array $ligne): ?string
    {
        foreach (['nom', 'prenom_nom', 'denomination'] as $cle) {
            if (filled($ligne[$cle] ?? null)) {
                return trim((string) $ligne[$cle]);
            }
        }

        return null;
    }

    /** Les nombres du questionnaire arrivent en chaîne, parfois avec des espaces insécables. */
    private function nombre(mixed $valeur): ?float
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        $propre = preg_replace('/[^\d.,-]/u', '', (string) $valeur);
        $propre = str_replace(',', '.', $propre);

        return is_numeric($propre) ? (float) $propre : null;
    }

    private function valeurNominale(mixed $capital, mixed $parts): ?float
    {
        $capital = (float) $capital;
        $parts = (int) $parts;

        return $parts > 0 ? round($capital / $parts, 2) : null;
    }
}
