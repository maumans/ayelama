<?php

namespace App\Http\Controllers;

use App\Models\Partie;
use App\Models\Societe;
use Illuminate\Http\Request;

/**
 * Registre des sociétés — recherche, création et correction d'une fiche sans quitter
 * l'écran d'où on la consulte.
 *
 * Calqué sur {@see ClientController}, qui résout le même problème pour les personnes :
 * mêmes conventions de réponse (JSON brut, pas de page Inertia), même factorisation des
 * règles entre `store()` et `update()`, même absence de `destroy()`.
 */
class SocieteController extends Controller
{
    /**
     * Recherche de sociétés pour l'auto-complétion (sélecteur de société de l'assistant de
     * dossier). Réponse JSON brute, pas une page Inertia.
     */
    public function autocomplete(Request $request)
    {
        $this->authorize('viewAny', Societe::class);

        $q = trim((string) $request->get('q', ''));

        $query = Societe::query()->actif()->with('dossier:id,reference');

        if (mb_strlen($q) < 2) {
            // Aucune recherche saisie : proposer les fiches les plus récentes plutôt qu'une
            // liste vide, pour permettre de parcourir le registre sans connaître déjà le nom
            // recherché (même parti pris que ClientController::autocomplete).
            $societes = $query->orderByDesc('created_at')->limit(20)->get();
        } else {
            $societes = $query->recherche($q)->orderBy('denomination')->limit(15)->get();
        }

        return response()->json(
            $societes->map(fn (Societe $societe) => $this->presenter($societe))
        );
    }

    /**
     * Fiche complète d'une société, avec les personnes connues de son dossier de
     * constitution.
     *
     * Appelée quand l'utilisateur rattache une société à un dossier de modification : c'est
     * ce qui permet de proposer les associés et gérants déjà fichés comme cédants ou gérant
     * sortant, plutôt que de les faire ressaisir — et donc d'éviter la création de fiches
     * clients concurrentes pour les mêmes personnes (décision #33).
     */
    public function show(Societe $societe)
    {
        $this->authorize('view', $societe);

        return response()->json([
            ...$this->presenter($societe),
            'personnesConnues' => $societe->associesConnus()
                ->map(fn (Partie $partie) => [
                    'partie_id' => $partie->id,
                    'client_id' => $partie->client_id,
                    'nom'       => $partie->nom,
                    'role'      => $partie->role,
                    'client'    => $partie->client,
                ])
                ->values(),
        ]);
    }

    /**
     * Règles partagées par store() et update() — la fiche société est le même objet qu'on
     * la crée depuis l'assistant de dossier ou qu'on la corrige ensuite.
     */
    private function regles(): array
    {
        return [
            'denomination'             => ['required', 'string', 'max:200'],
            'forme'                    => ['nullable', 'string', 'max:50'],
            'sigle'                    => ['nullable', 'string', 'max:50'],
            'rccm_numero'              => ['nullable', 'string', 'max:100'],
            'nif'                      => ['nullable', 'string', 'max:100'],
            'capital_chiffres'         => ['nullable', 'numeric', 'min:0'],
            'nombre_parts'             => ['nullable', 'integer', 'min:0'],
            'valeur_nominale_chiffres' => ['nullable', 'numeric', 'min:0'],
            'siege_quartier'           => ['nullable', 'string', 'max:100'],
            'siege_commune'            => ['nullable', 'string', 'max:100'],
            'siege_ville'              => ['nullable', 'string', 'max:100'],
            'email_societe'            => ['nullable', 'email', 'max:150'],
            // Même format guinéen que la fiche client — une seule convention pour tout le
            // répertoire, sinon un numéro accepté ici serait refusé là.
            'telephone_societe'        => ['nullable', 'string', 'max:25', 'regex:/^(?:\+?224|00224)?[\s.-]*6\d{2}(?:[\s.-]*\d{2}){3}$/'],
            'objet_social'             => ['nullable', 'string'],
            'duree'                    => ['nullable', 'integer', 'min:0'],
            // Une société ne peut pas avoir été constituée dans le futur, ni avant l'indépendance
            // guinéenne — au-delà, c'est une faute de frappe sur l'année.
            'date_constitution'        => ['nullable', 'date', 'before_or_equal:today', 'after:1958-01-01'],
            'notaire_origine'          => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Messages là où la tournure par défaut se lit mal — `before_or_equal:today` interpole le
     * littéral « today ».
     */
    private function messagesValidation(): array
    {
        return [
            'date_constitution.before_or_equal' => 'La date de constitution ne peut pas être dans le futur.',
            'date_constitution.after'           => 'La date de constitution semble erronée (avant 1958).',
            'telephone_societe.regex'           => 'Numéro guinéen attendu — par exemple 622 78 37 32.',
        ];
    }

    /**
     * Création d'une fiche depuis l'assistant, pour une société **absente du registre** —
     * l'étude traite aussi des modifications de sociétés qu'elle n'a pas constituées.
     * Réponse JSON (la fiche créée) pour rattachement immédiat, sans quitter l'assistant.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Societe::class);

        $societe = Societe::create($request->validate($this->regles(), $this->messagesValidation()));

        return response()->json($this->presenter($societe), 201);
    }

    /**
     * Correction d'une fiche société.
     *
     * Contrairement à {@see ClientController::update()}, aucune reprojection n'est
     * déclenchée : corriger la fiche ne doit pas réécrire rétroactivement les dossiers
     * passés. La projection `soc.*` d'un dossier est figée au moment où la société y est
     * rattachée, et n'évolue ensuite que par une **modification statutaire** — laquelle est
     * précisément l'objet d'un dossier, avec son PV, sa certification et ses formalités.
     * Réaligner en silence les actes d'un dossier en cours sur une correction de saisie
     * reviendrait à contourner ce circuit.
     */
    public function update(Request $request, Societe $societe)
    {
        $this->authorize('update', $societe);

        $societe->update($request->validate($this->regles(), $this->messagesValidation()));

        return response()->json($this->presenter($societe->fresh()));
    }

    /**
     * Forme envoyée au frontend : les colonnes, plus les libellés dérivés dont le
     * sélecteur a besoin pour afficher une carte de synthèse sans redemander le serveur.
     */
    private function presenter(Societe $societe): array
    {
        $societe->loadMissing('piecesConstitutives.versionActuelle');

        return [
            ...$societe->toArray(),
            'nom_complet'      => $societe->nomComplet(),
            'forme_label'      => $societe->formeLabel(),
            'dossier_origine'  => $societe->dossier?->only(['id', 'reference']),
            // Dirigeant en exercice, calculé : préremplit `soc.gerant_actuel` au rattachement plutôt
            // que de le laisser saisir alors qu'il est connu du registre.
            'gerant_actuel'    => $societe->gerantActuel(),
            // Dossier constitutif : seules les sociétés que l'étude n'a pas constituées doivent le
            // fournir — pour les autres, le dossier d'origine fait foi et la checklist ne s'affiche
            // pas du tout.
            'exige_pieces'     => $societe->exigePiecesConstitutives(),
            'pieces'           => $societe->exigePiecesConstitutives()
                ? $societe->piecesConstitutivesChecklist()
                : [],
            'pieces_manquantes' => $societe->exigePiecesConstitutives()
                ? $societe->piecesConstitutivesManquantes()
                : [],
        ];
    }
}
