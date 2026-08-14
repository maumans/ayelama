<?php

namespace App\Http\Controllers;

use App\Models\DocumentFichier;
use App\Models\DocumentVersion;
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

        $data = $request->validate([
            'statut'  => ['sometimes', 'in:a_editer,edite'],
            'nom'     => ['sometimes', 'string', 'max:200'],
            'fichier' => ['sometimes', 'nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,odt,xlsx,xls'],
        ]);

        if ($request->hasFile('fichier')) {
            $document->nouvelleVersion($request->file('fichier'), 'documents/' . $document->documentable->reference);
            unset($data['fichier']);
        }

        if (isset($data['statut']) && $data['statut'] === 'edite') {
            $data += ['edite_at' => now(), 'edite_par_id' => auth()->id()];
        }

        $document->update($data);

        return back()->with('success', 'Document mis à jour.');
    }

    public function regenerer(DocumentFichier $document, \App\Services\ActesGeneratorService $generatorService)
    {
        $this->refuserSiPieceDeRegistre($document);
        $dossier = $document->documentable;
        $this->authorize('genererDocuments', $dossier);
        abort_if($document->est_signe_cachete, 403, 'Document verrouillé : déjà signé/cacheté, non modifiable.');

        $dossier->load('questionnaire');

        if (! $generatorService->regenererDocument($document)) {
            return back()->with('error', "Aucun modèle actif trouvé pour « {$document->nom} ».");
        }

        JournalActivite::enregistrer(
            $dossier,
            "Document « {$document->nom} » régénéré depuis le modèle",
            'etape',
            []
        );

        return back()->with('success', "« {$document->nom} » régénéré avec succès.");
    }

    public function destroy(DocumentFichier $document)
    {
        $this->refuserSiPieceDeRegistre($document);
        $this->authorize('genererDocuments', $document->dossierGouvernant());
        abort_if($document->est_signe_cachete, 403, 'Document verrouillé : déjà signé/cacheté, non modifiable.');

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

    public function versions(DocumentFichier $document)
    {
        $this->authorize('view', $document->sujetAutorisation());

        return response()->json([
            'versions' => $document->versions()
                ->with('creePar:id,name')
                ->get()
                ->reverse()
                ->values()
                ->map(fn (DocumentVersion $v) => [
                    'id'            => $v->id,
                    'numero'        => $v->numero,
                    'est_actuelle'  => $v->id === $document->version_actuelle_id,
                    'nom_original'  => $v->nom_original,
                    'taille_octets' => $v->taille_octets,
                    'source'        => $v->source,
                    'cree_par'      => $v->creePar?->name,
                    'created_at'    => $v->created_at?->format('d/m/Y H:i'),
                    'url_download'  => route('documents.versions.telecharger', $v),
                ]),
        ]);
    }

    public function telechargerVersion(DocumentVersion $version)
    {
        $this->authorize('view', $version->documentFichier->sujetAutorisation());

        if (!$version->chemin_fichier || !Storage::disk('public')->exists($version->chemin_fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        $ext      = pathinfo($version->chemin_fichier, PATHINFO_EXTENSION);
        $filename = $version->documentFichier->nom . '_v' . $version->numero . ($ext ? '.' . $ext : '');

        return Storage::disk('public')->download($version->chemin_fichier, $filename);
    }

    /**
     * Dépôt de la version finale signée/cachetée (retour du circuit papier réel :
     * impression → envoi → signature/cachet → retour) — verrouille définitivement le
     * document (voir abort_if(est_signe_cachete) dans update/regenerer/destroy/restaurerVersion
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

    public function restaurerVersion(DocumentVersion $version)
    {
        $document = $version->documentFichier;
        $this->refuserSiPieceDeRegistre($document);
        $this->authorize('genererDocuments', $document->dossierGouvernant());
        abort_if($document->est_signe_cachete, 403, 'Document verrouillé : déjà signé/cacheté, non modifiable.');

        $document->restaurerVersion($version);

        JournalActivite::enregistrer(
            $document->dossierGouvernant(),
            "Document « {$document->nom} » restauré à la version {$version->numero}",
            'etape',
            []
        );

        return back()->with('success', "Version {$version->numero} restaurée.");
    }
}
