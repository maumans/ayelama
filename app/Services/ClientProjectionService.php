<?php

namespace App\Services;

use App\Enums\EtapeDossier;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\Partie;
use App\Models\Questionnaire;

/**
 * Projette les données d'identité des fiches Client dans le questionnaire d'un
 * dossier (`questionnaires.donnees`).
 *
 * La fiche Client est la source de vérité de l'identité d'une personne ; `donnees`
 * n'en est qu'une **projection dérivée**, conservée parce que
 * ActesGeneratorService reste un moteur générique clé/valeur : les modèles Word
 * attendent des balises ${pp.prenom_nom}, ${ger.piece_numero}… La projection
 * garantit que ces balises restent alimentées sans que l'utilisateur ressaisisse
 * une identité déjà enregistrée.
 *
 * Où écrire ? Le service ne connaît pas le schéma des questionnaires (il vit en
 * JavaScript, resources/js/data/questionnaires.js). C'est la Partie qui le lui
 * dit, via donnees_prefixe / donnees_bloc / donnees_index renseignés par le
 * frontend — voir la migration add_donnees_mapping_to_parties_table.
 *
 * Ce qu'il ne touche JAMAIS : toute clé absente de SUFFIXES_* reste intacte. Les
 * données propres à l'acte ne sont pas des données de personne et n'ont rien à
 * faire sur une fiche client — `parts_chiffres` (nombre de parts dans CETTE
 * société), `actions_chiffres`, `apport_chiffres`, `fonction` (au conseil
 * d'administration), `qualite` (du liquidateur dans cet acte), les indicateurs
 * `ger.est_different` / `soc.president_est_different`, et tous les `bq.*` de
 * crédit (montant, taux, rang hypothécaire). La même personne peut détenir 100
 * parts ici et 5 ailleurs.
 */
class ClientProjectionService
{
    /**
     * Suffixe de balise → attribut de la fiche Client, pour une personne physique.
     *
     * Miroir PHP assumé de mapClientToPrefixedFields() / mapClientToRepeatableItem()
     * (resources/js/lib/clientFields.js) : les deux doivent évoluer ensemble.
     * ClientProjectionTest verrouille cette correspondance.
     *
     * Les alias (`nom` en plus de `prenom_nom`, `siege_*` en plus de
     * `quartier`/`commune`/`ville`, `cni` en plus de `piece_numero`) existent parce
     * que les schémas de questionnaire ne nomment pas ces champs uniformément —
     * projeter les deux graphies évite un mapping par rôle.
     */
    private const SUFFIXES_PHYSIQUE = [
        'civilite'               => 'civilite',
        // `prenom_nom` reste projeté : les 63 modèles Word non normalisés l'utilisent.
        // C'est un accessor du modèle depuis la séparation nom/prénoms (règle 4), pas une
        // colonne — la projection lit un attribut, la distinction lui est transparente.
        'prenom_nom'             => 'prenom_nom',
        'nom'                    => 'prenom_nom',
        // Nouvelles balises de la règle 4 : `${pp.nom_famille}` est en CAPITALES, comme
        // l'exige le document. `${pp.identite_notariale}` compose « DIALLO Ibrahima ».
        'nom_famille'            => 'nom_famille_majuscule',
        'prenoms'                => 'prenoms',
        'identite_notariale'     => 'identite_notariale',
        'ne_a'                   => 'ne_a',
        'date_naissance'         => 'date_naissance',
        'nationalite'            => 'nationalite',
        'situation_matrimoniale' => 'situation_matrimoniale',
        'regime_matrimonial'     => 'regime_matrimonial',
        'piece_type'             => 'piece_type',
        'piece_numero'           => 'piece_numero',
        'cni'                    => 'piece_numero',
        'piece_delivree_le'      => 'piece_delivree_le',
        'piece_delivree_a'       => 'piece_delivree_a',
        'piece_expire_le'        => 'piece_expire_le',
    ];

    /** Suffixe → attribut, pour une personne morale. */
    private const SUFFIXES_MORALE = [
        'denomination'         => 'denomination',
        'prenom_nom'           => 'denomination',
        'nom'                  => 'denomination',
        'forme'                => 'forme',
        'rccm'                 => 'rccm',
        'cni'                  => 'rccm',
        'piece_numero'         => 'rccm',
        'representant_legal'   => 'representant_legal',
        'representant_nom'     => 'representant_legal',
        'representant_qualite' => 'representant_qualite',
        'nationalite'          => 'pays',
    ];

    /** Suffixe → attribut, communs aux deux types. */
    private const SUFFIXES_COMMUNS = [
        'quartier'       => 'quartier',
        'commune'        => 'commune',
        'demeurant_ville' => 'demeurant_ville',
        'ville'          => 'demeurant_ville',
        'siege_quartier' => 'quartier',
        'siege_commune'  => 'commune',
        'siege_ville'    => 'demeurant_ville',
        'pays'           => 'pays',
        'telephone'      => 'telephone',
        'email'          => 'email',
    ];

    public function __construct(private ActesGeneratorService $generateur) {}

    /**
     * Recalcule `donnees` du dossier depuis les fiches clients de ses parties.
     *
     * @return bool true si `donnees` a effectivement changé (permet à l'appelant
     *              de n'écrire et de ne régénérer que si nécessaire).
     */
    public function reprojeter(Dossier $dossier): bool
    {
        // Un dossier clôturé est un dossier scellé : sa vérité est celle du jour de
        // la clôture, une correction de fiche client ne doit plus le réécrire.
        if ($dossier->etape === EtapeDossier::Cloture) {
            return false;
        }

        $dossier->loadMissing(['questionnaire', 'parties.client']);

        $donnees = $dossier->questionnaire?->donnees ?? [];
        $avant   = $donnees;

        foreach ($dossier->parties as $partie) {
            if (!$partie->estProjetable() || !$partie->client) {
                continue;
            }

            $valeurs = $this->valeursPour($partie->client);

            if ($partie->donnees_bloc === null) {
                foreach ($valeurs as $suffixe => $valeur) {
                    $donnees["{$partie->donnees_prefixe}.{$suffixe}"] = $valeur;
                }
                continue;
            }

            // Bloc répétable : fusion dans l'item, jamais remplacement — sinon on
            // effacerait le nombre de parts / la fonction saisis sur cette ligne.
            $index = $partie->donnees_index ?? 0;
            $bloc  = $donnees[$partie->donnees_bloc] ?? [];
            if (!is_array($bloc)) {
                continue;
            }
            $bloc[$index] = array_merge(
                is_array($bloc[$index] ?? null) ? $bloc[$index] : [],
                $valeurs,
            );
            $donnees[$partie->donnees_bloc] = $bloc;
        }

        if ($donnees === $avant) {
            return false;
        }

        if ($dossier->questionnaire) {
            $dossier->questionnaire->update(['donnees' => $donnees]);
        } else {
            Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);
        }
        $dossier->unsetRelation('questionnaire');

        return true;
    }

    /**
     * Répercute une modification de fiche client sur tous les dossiers qui la
     * référencent : reprojection puis régénération des actes non verrouillés.
     *
     * Chaque dossier touché reçoit une entrée de journal : en notarial, une
     * modification déclenchée à distance (depuis le Répertoire, hors du dossier)
     * ne doit jamais être invisible pour l'équipe qui le suit.
     *
     * @return array<int, string> Références des dossiers effectivement mis à jour.
     */
    public function reprojeterDossiersDuClient(Client $client): array
    {
        $dossiers = Dossier::query()
            ->whereNot('etape', EtapeDossier::Cloture->value)
            ->whereHas('parties', fn ($q) => $q->projetables()->where('client_id', $client->id))
            ->with(['questionnaire', 'parties.client', 'documents'])
            ->get();

        $touches = [];

        foreach ($dossiers as $dossier) {
            if (!$this->reprojeter($dossier)) {
                continue;
            }

            $regeneres = $this->regenererDocumentsNonVerrouilles($dossier);

            JournalActivite::enregistrer(
                $dossier,
                "Fiche client « {$client->nomComplet()} » modifiée — questionnaire mis à jour"
                    . ($regeneres > 0 ? ", {$regeneres} acte(s) régénéré(s)" : ''),
                'modification',
                ['client_id' => $client->id, 'documents_regeneres' => $regeneres],
            );

            $touches[] = $dossier->reference;
        }

        return $touches;
    }

    /**
     * Régénère les actes du dossier depuis leur modèle, en épargnant ceux qui sont
     * signés/cachetés — ceux-là portent la vérité du jour de la signature et sont
     * déjà verrouillés côté serveur (voir DocumentController).
     *
     * Extrait de DossierController::updateQuestionnaire(), qui appliquait déjà
     * exactement cette règle.
     */
    public function regenererDocumentsNonVerrouilles(Dossier $dossier): int
    {
        $dossier->loadMissing(['questionnaire', 'documents']);

        $regeneres = 0;
        foreach ($dossier->documents as $document) {
            if ($document->est_signe_cachete) {
                continue;
            }
            if ($this->generateur->regenererDocument($document)) {
                $regeneres++;
            }
        }

        return $regeneres;
    }

    /**
     * Valeurs projetables d'un client, indexées par suffixe de balise. Les valeurs
     * vides sont omises : la projection complète, elle n'efface pas.
     *
     * @return array<string, string>
     */
    private function valeursPour(Client $client): array
    {
        $table = $client->estPersonnePhysique()
            ? self::SUFFIXES_PHYSIQUE
            : self::SUFFIXES_MORALE;

        $valeurs = [];

        foreach ($table + self::SUFFIXES_COMMUNS as $suffixe => $attribut) {
            $valeur = $client->{$attribut};

            // Les dates sont castées en Carbon sur le modèle : les modèles Word
            // attendent une chaîne, et le frontend un format ISO qu'il sait
            // réafficher (voir isoDateToFR dans resources/js/lib/dates.js).
            if ($valeur instanceof \DateTimeInterface) {
                $valeur = $valeur->format('Y-m-d');
            }

            if ($valeur !== null && $valeur !== '') {
                $valeurs[$suffixe] = (string) $valeur;
            }
        }

        if (!$client->estPersonnePhysique()) {
            // Aucune colonne `civilite` pour une personne morale : les schémas de
            // questionnaire utilisent la valeur « Société » comme marqueur de type
            // (voir ASSOCIE_SCHEMA), et mapClientToRepeatableItem fait de même.
            $valeurs['civilite']      = 'Société';
            $valeurs['type_personne'] = 'Personne morale';
        } else {
            $valeurs['type_personne'] = 'Personne physique';
        }

        // `adresse` / `domicile` / `siege` : certains schémas n'ont qu'un champ
        // d'adresse en texte libre là où la fiche client la stocke éclatée.
        $composite = collect([$client->quartier, $client->commune, $client->demeurant_ville])
            ->filter()
            ->join(', ');
        if ($composite !== '') {
            $valeurs['adresse']  = $composite;
            $valeurs['domicile'] = $composite;
        }
        if (filled($client->siege) || $composite !== '') {
            $valeurs['siege'] = $client->siege ?: $composite;
        }

        return $valeurs;
    }

    /**
     * Suffixes que la projection est susceptible d'écrire — exposé pour les tests
     * et pour vérifier qu'aucune donnée propre à l'acte n'y figure.
     *
     * @return array<int, string>
     */
    public static function suffixesProjetes(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::SUFFIXES_PHYSIQUE),
            array_keys(self::SUFFIXES_MORALE),
            array_keys(self::SUFFIXES_COMMUNS),
            ['type_personne', 'adresse', 'domicile', 'siege'],
        )));
    }
}
