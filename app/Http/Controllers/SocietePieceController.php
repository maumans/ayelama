<?php

namespace App\Http\Controllers;

use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\Societe;
use Illuminate\Http\Request;

/**
 * Dossier constitutif d'une société du registre : statuts en vigueur, RCCM, NIF, actes
 * modificatifs antérieurs…
 *
 * Nécessaire pour les sociétés que l'étude **n'a pas constituées** : aucune de ces pièces n'existe
 * alors en base, et un dossier de modification partirait de zéro documentaire — jusqu'à produire
 * des « statuts mis à jour » depuis un gabarit générique dont ni les articles ni la numérotation ne
 * correspondent aux statuts réels de la société.
 *
 * Calqué sur {@see PartieController::televerserPieceRequise()}, y compris le `firstOrCreate` par
 * catégorie : re-téléverser crée une **nouvelle version** au lieu de dupliquer la ligne.
 *
 * Contrairement aux pièces d'un dossier, celles-ci s'autorisent sur `update` de la **Societe** :
 * elles appartiennent au registre, dont la tenue est réservée aux rôles pouvant ouvrir un dossier
 * (voir {@see \App\Policies\SocietePolicy}) — un formaliste ne verse pas les statuts d'une société.
 */
class SocietePieceController extends Controller
{
    /**
     * Téléverse (ou remplace) une pièce de la checklist constitutive.
     *
     * `dossier_reference` est optionnel : quand le dépôt vient de la fiche d'un dossier ou de
     * l'assistant, on journalise l'opération **sur ce dossier**. Le registre n'a pas de journal
     * propre, et une pièce versée en silence sur une fiche de référence notariale serait invisible
     * pour l'équipe qui suit le dossier — même raisonnement que pour la modification d'une fiche
     * client depuis le Répertoire.
     */
    public function televerser(Request $request, Societe $societe, string $categorie)
    {
        $this->authorize('update', $societe);

        abort_unless(array_key_exists($categorie, Societe::PIECES_CONSTITUTIVES), 404);

        $data = $request->validate([
            // `docx` en tête de liste sans être imposé : le client apporte souvent un PDF, qui reste
            // une pièce valable au dossier — il ne pourra simplement pas servir de gabarit aux
            // statuts mis à jour (voir Societe::gabaritStatutsDocx()).
            'fichier'           => ['required', 'file', 'max:20480', 'mimes:docx,doc,pdf,jpg,jpeg,png'],
            'dossier_reference' => ['nullable', 'string', 'exists:dossiers,reference'],
        ]);

        $definition = Societe::PIECES_CONSTITUTIVES[$categorie];

        $piece = $societe->piecesConstitutives()->firstOrCreate(
            ['categorie' => $categorie],
            [
                'nom'           => $definition['label'],
                'est_requis'    => $definition['requis'],
                'created_by_id' => auth()->id(),
            ],
        );

        $piece->nouvelleVersion($request->file('fichier'), $societe->repertoirePieces());

        $this->journaliser(
            $data['dossier_reference'] ?? null,
            sprintf(
                '« %s » versé au dossier constitutif de la société « %s »',
                $definition['label'],
                $societe->denomination,
            ),
            $societe,
        );

        return back()->with('success', "« {$definition['label']} » téléversé.");
    }

    /**
     * Retire une pièce constitutive et ses versions.
     *
     * Contrairement à la suppression d'un acte de dossier, aucune notion de document
     * signé/cacheté n'intervient : ces pièces sont des justificatifs fournis, pas des actes que
     * l'étude produit et fait signer.
     */
    public function destroy(Request $request, DocumentFichier $piece)
    {
        abort_unless($piece->estPieceDeRegistre(), 404);

        $societe = $piece->documentable;
        $this->authorize('update', $societe);

        $request->validate([
            'dossier_reference' => ['nullable', 'string', 'exists:dossiers,reference'],
        ]);

        $nom = $piece->nom;
        $piece->supprimerAvecFichiers();

        $this->journaliser(
            $request->input('dossier_reference'),
            sprintf('« %s » retiré du dossier constitutif de la société « %s »', $nom, $societe->denomination),
            $societe,
        );

        return back()->with('success', "« {$nom} » retiré.");
    }

    /**
     * Journalise sur le dossier depuis lequel l'opération a été faite, s'il est connu.
     *
     * Sans référence de dossier (appel direct à l'API), on ne journalise rien plutôt que d'inventer
     * un dossier : `JournalActivite` est le journal **d'un dossier**, pas un journal applicatif.
     */
    private function journaliser(?string $reference, string $action, Societe $societe): void
    {
        if (blank($reference)) {
            return;
        }

        $dossier = Dossier::where('reference', $reference)->first();

        if (!$dossier) {
            return;
        }

        JournalActivite::enregistrer($dossier, $action, 'modification', ['societe_id' => $societe->id]);
    }
}
