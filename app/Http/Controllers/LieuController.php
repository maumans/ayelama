<?php

namespace App\Http\Controllers;

use App\Models\Lieu;
use App\Support\Normalisation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Alimente la cascade ville → commune → quartier des formulaires de saisie.
 *
 * Les valeurs restent stockées par leur **nom** dans les questionnaires et les fiches (voir la
 * migration `create_lieux_table`) : ce contrôleur ne sert donc qu'à proposer les bonnes options, pas
 * à établir une clé étrangère.
 */
class LieuController extends Controller
{
    /**
     * Lieux d'un niveau donné, éventuellement sous un parent désigné par son **nom**.
     *
     * Le parent est passé par son nom et non son identifiant : c'est ce que le formulaire détient
     * (`soc.siege_ville = "Conakry"`), et cela évite d'imposer aux questionnaires de porter des
     * identifiants qu'ils n'ont jamais eus.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lieu::class);

        $data = $request->validate([
            'niveau' => ['required', Rule::in(array_keys(Lieu::NIVEAU_ENFANT))],
            'parent' => ['nullable', 'string', 'max:120'],
        ]);

        $niveau = $data['niveau'];
        $parent = $data['parent'] ?? null;

        // Un niveau qui attend un parent sans en recevoir ne doit **rien** renvoyer plutôt que tout :
        // proposer les 54 quartiers du pays quand aucune commune n'est choisie recréerait
        // exactement l'incohérence que la cascade supprime.
        if ($niveau !== Lieu::NIVEAU_VILLE && blank($parent)) {
            return response()->json(['lieux' => []]);
        }

        $lieux = Lieu::actif()->niveau($niveau)
            ->when($niveau === Lieu::NIVEAU_VILLE, fn ($q) => $q->whereNull('parent_id'))
            ->when($parent, fn ($q) => $q->whereHas(
                'parent',
                fn ($qq) => $qq->where('nom_normalise', Normalisation::comparable($parent)),
            ))
            ->orderBy('nom')
            ->get(['id', 'nom', 'a_verifier']);

        return response()->json([
            'lieux' => $lieux->map(fn (Lieu $l) => [
                'nom'        => $l->nom,
                // Signalé à l'écran : les quartiers amorcés au seeder ne sont pas garantis
                // exhaustifs ni à jour, l'étude doit pouvoir les distinguer de ses propres saisies.
                'a_verifier' => $l->a_verifier,
            ]),
        ]);
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
