<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\DossierBrouillon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DossierBrouillonController extends Controller
{
    /**
     * Un brouillon peut être repris par tout utilisateur autorisé à créer des
     * dossiers (Clerc, Notaire, Administrateur) — pas seulement son auteur.
     * Les clercs et rédacteurs doivent pouvoir reprendre les saisies inachevées
     * de leurs collègues pour fluidifier le travail d'équipe.
     */
    private function autoriser(DossierBrouillon $brouillon): void
    {
        abort_unless(auth()->user()->can('create', Dossier::class), 403);
    }

    /**
     * Enregistre (ou met à jour) le brouillon en cours.
     *
     * `etat` arrive en JSON encodé : la requête est un multipart (elle transporte
     * les pièces déjà sélectionnées), et l'état de l'assistant est trop imbriqué
     * pour survivre à une sérialisation en champs de formulaire.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Dossier::class);

        $data = $request->validate([
            'brouillon_id' => ['nullable', 'integer', 'exists:dossier_brouillons,id'],
            'etat'         => ['required', 'string'],
            'type_acte_id' => ['nullable', 'integer', 'exists:types_actes,id'],
            'libelle'      => ['nullable', 'string', 'max:255'],
            'pieces'       => ['nullable', 'array'],
            'pieces.*'     => ['array'],
            'pieces.*.*'   => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $etat = json_decode($data['etat'], true);
        if (!is_array($etat)) {
            return response()->json(['message' => "L'état du brouillon est illisible."], 422);
        }

        // ?? null : `validate()` n'expose pas une clé absente de la requête, même
        // déclarée nullable.
        $brouillon = ($data['brouillon_id'] ?? null)
            ? DossierBrouillon::find($data['brouillon_id'])
            : null;
        if ($brouillon) {
            $this->autoriser($brouillon);
        } else {
            // Créé avec un état vide d'abord : les pièces sont rangées dans un
            // répertoire nommé d'après l'id, qui n'existe pas avant l'insertion.
            $brouillon = DossierBrouillon::create([
                'user_id' => auth()->id(),
                'etat'    => [],
            ]);
        }

        $etat['piecesBrouillon'] = $this->rangerPieces(
            $request,
            $brouillon,
            $etat['piecesBrouillon'] ?? [],
        );

        $brouillon->update([
            'type_acte_id' => $data['type_acte_id'] ?? null,
            'libelle'      => $data['libelle'] ?? null,
            'etat'         => $etat,
        ]);

        return response()->json($this->versArray($brouillon->fresh()));
    }

    /**
     * Déplace les pièces nouvellement sélectionnées vers le répertoire privé du
     * brouillon et les fusionne avec celles déjà téléversées.
     *
     * Les pièces déjà présentes sont conservées telles quelles : l'utilisateur qui
     * réenregistre son brouillon ne renvoie que ce qu'il vient d'ajouter. Un
     * remplacement supprime l'ancien fichier pour ne pas laisser d'orphelin.
     *
     * @param  array<string, array<string, array{chemin: string, nom: string}>>  $deja
     * @return array<string, array<string, array{chemin: string, nom: string}>>
     */
    private function rangerPieces(Request $request, DossierBrouillon $brouillon, array $deja): array
    {
        foreach ($request->file('pieces', []) as $groupe => $fichiers) {
            foreach ($fichiers as $cle => $fichier) {
                $ancien = $deja[$groupe][$cle]['chemin'] ?? null;
                if ($ancien) {
                    Storage::disk('local')->delete($ancien);
                }

                $deja[$groupe][$cle] = [
                    'chemin' => $fichier->store($brouillon->repertoire(), 'local'),
                    'nom'    => $fichier->getClientOriginalName(),
                ];
            }
        }

        // Pièces retirées côté assistant : l'état reçu ne les mentionne plus, mais
        // leur fichier serait resté sur le disque.
        $conserves = collect($deja)->flatMap(fn ($g) => collect($g)->pluck('chemin'))->all();
        foreach (Storage::disk('local')->files($brouillon->repertoire()) as $fichier) {
            if (!in_array($fichier, $conserves, true)) {
                Storage::disk('local')->delete($fichier);
            }
        }

        return $deja;
    }

    public function destroy(DossierBrouillon $brouillon)
    {
        $this->autoriser($brouillon);
        $brouillon->supprimerAvecFichiers();

        return response()->json(['ok' => true]);
    }

    /**
     * Tous les brouillons accessibles aux utilisateurs autorisés à créer des
     * dossiers — partagés à l'assistant par DossierController::create().
     */
    public static function pourUtilisateur(int $userId): array
    {
        return DossierBrouillon::with('typeActe:id,code,label', 'user:id,name,initiales')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (DossierBrouillon $b) => self::versArray($b))
            ->all();
    }

    private static function versArray(DossierBrouillon $brouillon): array
    {
        $etat = $brouillon->etat ?? [];

        return [
            'id'            => $brouillon->id,
            'libelle'       => $brouillon->libelle,
            'typeActeLabel' => $brouillon->typeActe?->label,
            'typeActeCode'  => $brouillon->typeActe?->code,
            'etat'          => $etat,
            'nbPieces'      => collect($etat['piecesBrouillon'] ?? [])->sum(fn ($g) => count($g)),
            'modifie_le'    => $brouillon->updated_at?->format('d/m/Y à H:i'),
            'auteur'        => $brouillon->user?->name,
            'auteur_initiales' => $brouillon->user?->initiales,
        ];
    }
}
