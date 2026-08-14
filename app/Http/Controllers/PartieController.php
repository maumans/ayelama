<?php

namespace App\Http\Controllers;

use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\Partie;
use Illuminate\Http\Request;

class PartieController extends Controller
{
    /**
     * Ajoute une personne au dossier en dehors des rôles déclarés par le
     * questionnaire (ex. accompagnateur, témoin) — indépendant du flux de
     * sauvegarde du questionnaire pour ne pas interférer avec la
     * synchronisation par « managedRoles » de DossierController::updateQuestionnaire().
     */
    public function store(Request $request, Dossier $dossier)
    {
        // Même règle que DossierController::updateQuestionnaire(), qui gère aussi la
        // liste des parties : la composition de l'acte est arrêtée à l'Édition. Ajouter
        // une partie après la certification changerait les personnes à l'acte sans
        // qu'aucun contrôle ne l'ait vue.
        $this->authorize('modifierQuestionnaire', $dossier);

        $data = $request->validate([
            'nom'         => ['required', 'string', 'max:200'],
            'role'        => ['required', 'string', 'max:100'],
            'client_id'   => ['nullable', 'integer', 'exists:clients,id'],
            'cni'         => ['nullable', 'string', 'max:50'],
            'telephone'   => ['nullable', 'string', 'max:20'],
            'adresse'     => ['nullable', 'string', 'max:500'],
            'email'       => ['nullable', 'email', 'max:200'],
        ]);

        $dossier->parties()->create($data);

        return back()->with('success', 'Personne ajoutée au dossier.');
    }

    public function destroy(Partie $partie)
    {
        $this->authorize('modifierQuestionnaire', $partie->dossier);

        foreach ($partie->pieces as $piece) {
            $piece->supprimerAvecFichiers();
        }

        $partie->delete();

        return back()->with('success', 'Personne retirée du dossier.');
    }

    public function uploaderPhoto(Request $request, Partie $partie)
    {
        $this->authorize('gererPieces', $partie->dossier);

        $request->validate([
            'fichier' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $photo = $partie->pieces()->where('categorie', 'photo')->first()
            ?? $partie->pieces()->create(['nom' => 'Photo', 'categorie' => 'photo']);

        $photo->nouvelleVersion($request->file('fichier'), 'parties/' . $partie->dossier->reference);

        return back()->with('success', 'Photo mise à jour.');
    }

    public function uploaderPiece(Request $request, Partie $partie)
    {
        $this->authorize('gererPieces', $partie->dossier);

        $data = $request->validate([
            'nom'     => ['required', 'string', 'max:200'],
            'fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $piece = $partie->pieces()->create([
            'nom'       => $data['nom'],
            'categorie' => 'piece_justificative',
        ]);

        $piece->nouvelleVersion($request->file('fichier'), 'parties/' . $partie->dossier->reference);

        return back()->with('success', 'Pièce ajoutée.');
    }

    /**
     * Téléversement d'une pièce de la checklist requise (CNI, certificat de résidence,
     * 2ᵉ photo d'identité, statuts…, selon le rôle et le type de personne — voir
     * Partie::piecesRequisesDefinition()). `firstOrCreate` : re-téléverser remplace la
     * version courante sans dupliquer la ligne.
     */
    public function televerserPieceRequise(Request $request, Partie $partie, string $categorie)
    {
        $this->authorize('gererPieces', $partie->dossier);

        $requis = $partie->piecesRequisesDefinition();
        abort_unless(array_key_exists($categorie, $requis), 404);

        $request->validate([
            'fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $piece = $partie->pieces()->firstOrCreate(
            ['categorie' => $categorie],
            ['nom' => $requis[$categorie], 'est_requis' => true]
        );

        $piece->nouvelleVersion($request->file('fichier'), 'parties/' . $partie->dossier->reference);

        return back()->with('success', 'Pièce téléversée.');
    }

    /**
     * Reprend une pièce que cette même personne a déjà fournie dans un autre dossier.
     *
     * Évite de redemander une CNI que l'étude détient déjà — cas constaté sur un dossier de
     * modification dont le souscripteur avait tout déposé à la constitution de la même société.
     *
     * ⚠️ `source_id` est **vérifié contre `piecesReprenables()`**, jamais accepté tel quel : sans
     * ce contrôle, la route permettrait de copier n'importe quelle pièce de n'importe quel dossier
     * vers le sien.
     */
    public function reprendrePiece(Request $request, Partie $partie, string $categorie)
    {
        $this->authorize('gererPieces', $partie->dossier);

        $data = $request->validate(['source_id' => ['required', 'integer']]);

        $reprenables = $partie->piecesReprenables();
        abort_unless(
            ($reprenables[$categorie]['piece_id'] ?? null) === (int) $data['source_id'],
            403,
            "Cette pièce n'est pas reprenable pour cette personne.",
        );

        $partie->reprendrePiece($categorie, DocumentFichier::findOrFail($data['source_id']));

        JournalActivite::enregistrer(
            $partie->dossier,
            sprintf(
                '« %s » de %s reprise depuis le dossier %s',
                $partie->piecesRequisesDefinition()[$categorie],
                $partie->nom,
                $reprenables[$categorie]['dossier'] ?? '—',
            ),
            'etape',
            ['partie_id' => $partie->id, 'categorie' => $categorie],
        );

        return back()->with('success', 'Pièce reprise.');
    }

    /**
     * Reprend d'un coup toutes les pièces disponibles pour cette personne.
     *
     * Le geste courant : une personne déjà connue de l'étude a rarement une seule pièce à
     * reprendre, et les cliquer une à une n'apporte rien.
     */
    public function reprendreTout(Partie $partie)
    {
        $this->authorize('gererPieces', $partie->dossier);

        $reprenables = $partie->piecesReprenables();

        if ($reprenables === []) {
            return back()->with('error', 'Aucune pièce à reprendre pour cette personne.');
        }

        foreach ($reprenables as $categorie => $source) {
            $partie->reprendrePiece($categorie, DocumentFichier::findOrFail($source['piece_id']));
        }

        JournalActivite::enregistrer(
            $partie->dossier,
            sprintf(
                '%d pièce(s) de %s reprises depuis un dossier antérieur',
                count($reprenables),
                $partie->nom,
            ),
            'etape',
            ['partie_id' => $partie->id, 'categories' => array_keys($reprenables)],
        );

        return back()->with('success', count($reprenables) . ' pièce(s) reprise(s).');
    }
}
