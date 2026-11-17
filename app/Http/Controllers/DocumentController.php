<?php

namespace App\Http\Controllers;

use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\RevisionPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Refuse les actions de ce contrôleur sur une **pièce de registre** (statuts, RCCM… rattachés à
     * une Societe et non à un dossier).
     *
     * Ces actions sont toutes conçues pour un document de dossier : elles autorisent sur
     * `genererDocuments`, journalisent sur le dossier, ou touchent au circuit de signature — rien
     * de tout cela n'a de sens pour une pièce que l'étude n'a pas produite et qui sert à plusieurs
     * dossiers. Un message explicite plutôt qu'un `authorize(..., null)` illisible.
     */
    private function refuserSiPieceDeRegistre(DocumentFichier $document): void
    {
        abort_if(
            $document->estPieceDeRegistre(),
            403,
            'Cette pièce appartient au dossier constitutif de la société : elle se gère depuis le registre, pas depuis le dossier.',
        );
    }

    public function store(Request $request, Dossier $dossier)
    {
        $this->authorize('genererDocuments', $dossier);

        $data = $request->validate([
            'nom'       => ['required', 'string', 'max:200'],
            'categorie' => ['required', 'in:acte_principal,annexe,procedure,lettre,recepisse'],
            'fichier'   => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,odt,xlsx,xls'],
        ]);

        $document = $dossier->documents()->create([
            'nom'           => $data['nom'],
            'categorie'     => $data['categorie'],
            'statut'        => 'a_editer',
            'created_by_id' => auth()->id(),
        ]);

        if ($request->hasFile('fichier')) {
            $document->nouvelleVersion($request->file('fichier'), 'documents/' . $dossier->reference);
        }

        return back()->with('success', 'Document ajouté.');
    }

    public function update(Request $request, DocumentFichier $document)
    {
        $this->refuserSiPieceDeRegistre($document);
        $this->authorize('genererDocuments', $document->documentable);
        abort_if($document->est_signe_cachete, 403, 'Document verrouillé : déjà signé/cacheté, non modifiable.');

        // Le remplacement du fichier ne passe plus par ici (2026-11-16) : il se fait par
        // `corriger()`, seule voie qui journalise et périme la certification. Ce champ acceptait un
        // fichier sans qu'aucun écran ne l'appelle — une seconde porte, plus large, vers le même effet.
        $data = $request->validate([
            'statut'  => ['sometimes', 'in:a_editer,edite'],
            'nom'     => ['sometimes', 'string', 'max:200'],
        ]);

        if (isset($data['statut']) && $data['statut'] === 'edite') {
            $data += ['edite_at' => now(), 'edite_par_id' => auth()->id()];
        }

        $document->update($data);

        return back()->with('success', 'Document mis à jour.');
    }

    /**
     * Dépôt d'une **version corrigée** d'un acte (retour R19, 2026-11-16) — par le rédacteur, le
     * certificateur ou l'administrateur, voir `DocumentFichierPolicy::corriger()`.
     *
     * Remplace la régénération manuelle, retirée le même jour (retour R10) : régénérer repartait du
     * modèle et **écrasait** une correction faite à la main. Corriger, c'est déposer le fichier
     * retravaillé — l'historique des versions garde le précédent, et la restauration reste possible.
     *
     * Le point de certification du document est **périmé** : la version certifiée n'est plus celle
     * du dossier, le certificateur doit se prononcer de nouveau.
     */
    public function corriger(Request $request, DocumentFichier $document)
    {
        $this->refuserSiPieceDeRegistre($document);
        $this->authorize('corriger', $document);

        $request->validate([
            'fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,odt'],
        ]);

        $dossier = $document->documentable;
        $version = $document->nouvelleVersion($request->file('fichier'), 'documents/' . $dossier->reference);
        RevisionPoint::perimerPour($document, $dossier);

        JournalActivite::enregistrer(
            $dossier,
            "Version corrigée de « {$document->nom} » déposée (v{$version->numero})",
            'modification',
            ['document_id' => $document->id, 'version' => $version->numero],
        );

        return back()->with('success', "Version corrigée de « {$document->nom} » enregistrée.");
    }

    public function destroy(DocumentFichier $document)
    {
        $this->refuserSiPieceDeRegistre($document);
        abort_if($document->est_signe_cachete, 403, 'Document verrouillé : déjà signé/cacheté, non modifiable.');
        // Par document et non par dossier : une pièce de partie suit `gererPieces`, un acte
        // `genererDocuments` — voir DocumentFichierPolicy::supprimer().
        $this->authorize('supprimer', $document);

        RevisionPoint::where('point_id', (string) $document->id)
            ->whereHas('revision', fn ($q) => $q->where('dossier_id', $document->documentable_id))
            ->delete();

        $document->supprimerAvecFichiers();

        return back()->with('success', 'Document supprimé.');
    }

    public function download(DocumentFichier $document)
    {
        $this->authorize('view', $document->sujetAutorisation());

        $chemin = $document->versionActuelle?->chemin_fichier;
        if (!$chemin || !Storage::disk('public')->exists($chemin)) {
            abort(404, 'Fichier introuvable.');
        }

        $ext      = pathinfo($chemin, PATHINFO_EXTENSION);
        $filename = $document->nom . ($ext ? '.' . $ext : '');

        return Storage::disk('public')->download($chemin, $filename);
    }

    public function preview(DocumentFichier $document)
    {
        $this->authorize('view', $document->sujetAutorisation());

        $chemin = $document->versionActuelle?->chemin_fichier;
        if (!$chemin || !Storage::disk('public')->exists($chemin)) {
            abort(404, 'Fichier introuvable.');
        }

        $path     = Storage::disk('public')->path($chemin);
        $mime     = mime_content_type($path) ?: 'application/octet-stream';
        $filename = basename($chemin);

        return response()->file($path, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Dépôt de la version finale signée/cachetée (retour du circuit papier réel :
     * impression → envoi → signature/cachet → retour) — verrouille définitivement le
     * document (voir abort_if(est_signe_cachete) dans update/corriger/destroy
     * ci-dessus, qui empêchent toute modification ultérieure même via appel direct à l'API).
     */
    public function televerserSigne(Request $request, DocumentFichier $document)
    {
        $this->refuserSiPieceDeRegistre($document);
        $dossier = $document->documentable;
        $this->authorize('cloturerDocuments', $dossier);
        abort_if($document->est_signe_cachete, 403, 'Document déjà signé/cacheté — verrouillé, non modifiable.');

        $request->validate([
            'fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,docx'],
        ]);

        $document->nouvelleVersion($request->file('fichier'), 'documents/' . $dossier->reference, ['source' => 'signe_cachete']);
        $document->update([
            'est_signe_cachete'    => true,
            'signe_cachete_at'     => now(),
            'signe_cachete_par_id' => auth()->id(),
        ]);

        JournalActivite::enregistrer(
            $dossier,
            "Document « {$document->nom} » déposé signé/cacheté (verrouillé)",
            'etape',
            []
        );

        return back()->with('success', "« {$document->nom} » enregistré comme signé/cacheté — verrouillé.");
    }

}
