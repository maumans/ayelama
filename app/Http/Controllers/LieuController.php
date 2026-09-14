<?php

namespace App\Http\Controllers;

use App\Models\Lieu;
use App\Support\Normalisation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Alimente la cascade ville → commune → quartier des formulaires de saisie.
 *
 * Le référentiel part **en une seule réponse** (`referentiel()`) et non plus niveau par niveau :
 * la cascade se résout ensuite de mémoire côté navigateur (voir resources/js/lib/referentielLieux.js).
 *
 * Les valeurs restent stockées par leur **nom** dans les questionnaires et les fiches (voir la
 * migration `create_lieux_table`) : ce contrôleur ne sert donc qu'à proposer les bonnes options, pas
 * à établir une clé étrangère.
 */
class LieuController extends Controller
{
    /**
     * Le référentiel **entier**, en une seule réponse.
     *
     * Remplace un point d'entrée qui servait un niveau à la fois. La cascade l'appelait **par champ
     * et par changement de parent** : sur le questionnaire de modification, qui porte 18 champs
     * géographiques, cela faisait jusqu'à 18 requêtes à l'ouverture puis une par choix de ville ou
     * de commune. C'est cette multiplication que l'étude percevait, et non le volume — le
     * référentiel entier pèse 5 Ko, moins de 15 Ko compressé même à 2 400 lieux.
     *
     * Servi depuis le cache (voir `Lieu::referentielComplet()`, périmé à chaque écriture d'un lieu)
     * et accompagné d'un `ETag` : un navigateur déjà chaud reçoit un 304 sans corps.
     */
    public function referentiel(Request $request)
    {
        $this->authorize('viewAny', Lieu::class);

        $referentiel = Lieu::referentielComplet();
        $charge = json_encode($referentiel, JSON_UNESCAPED_UNICODE);
        $etag = '"' . md5($charge) . '"';

        // 304 avant de composer la réponse : rien à sérialiser, rien à transférer.
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($charge, 200)
            ->header('Content-Type', 'application/json')
            ->header('ETag', $etag)
            // `private` : le référentiel n'est servi qu'aux écrans authentifiés, il n'a pas à être
            // mis en cache par un intermédiaire partagé. `must-revalidate` pour que l'ETag serve.
            ->header('Cache-Control', 'private, must-revalidate');
    }

    /**
     * Ajoute un lieu manquant, sans quitter le formulaire.
     *
     * Un clerc devant un client réel ne doit pas être bloqué par un quartier absent — même parti que
     * la création d'un client ou d'une société hors registre : le lieu sert immédiatement.
     *
     * Sert **deux** appelants : les formulaires de saisie, et l'écran du référentiel. D'où le
     * `a_verifier` calculé plus bas selon qui ajoute.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Lieu::class);

        $data = $request->validate([
            'niveau' => ['required', Rule::in(array_keys(Lieu::NIVEAU_ENFANT))],
            'nom'    => ['required', 'string', 'max:120'],
            'parent' => ['nullable', 'string', 'max:120'],
        ]);

        $parent = null;

        if ($data['niveau'] !== Lieu::NIVEAU_VILLE) {
            // Le parent doit exister **et** admettre ce niveau d'enfant : sans ce contrôle, un
            // quartier pourrait être rattaché à une ville, et la cascade proposerait ensuite des
            // quartiers là où l'on attend des communes.
            $niveauParent = array_search($data['niveau'], Lieu::NIVEAU_ENFANT, true);
            $parent = Lieu::parNom($data['parent'] ?? null, (string) $niveauParent);

            abort_unless(
                $parent && $parent->accepteEnfant($data['niveau']),
                422,
                "Le lieu parent est introuvable ou n'admet pas ce niveau.",
            );
        }

        // « À vérifier » dépend de **qui** ajoute. Un clerc qui crée un lieu en pleine saisie de
        // dossier fait un geste utile mais non validé — il entre donc dans la liste de travail de
        // l'administrateur. Celui-ci, lorsqu'il ajoute depuis l'écran du référentiel, **est** cette
        // validation : marquer son propre ajout à vérifier le renverrait à lui-même.
        $aVerifier = ! auth()->user()->can('update', new Lieu());

        $lieu = Lieu::firstOrCreate(
            [
                'parent_id'     => $parent?->id,
                'niveau'        => $data['niveau'],
                'nom_normalise' => Normalisation::comparable($data['nom']),
            ],
            [
                'nom'           => trim($data['nom']),
                'a_verifier'    => $aVerifier,
                'created_by_id' => auth()->id(),
            ],
        );

        return response()->json(['nom' => $lieu->nom, 'a_verifier' => $lieu->a_verifier], 201);
    }
}
