<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClotureController;
use App\Http\Controllers\CourrierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DossierBrouillonController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\FormaliteController;
use App\Http\Controllers\GedController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\ModeleActeController;
use App\Http\Controllers\ModeleCourrierController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartieController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\RepertoireController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SocieteController;
use App\Http\Controllers\TypeActeController;
use App\Http\Controllers\SocietePieceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Demande externe (lien client public, sans authentification) — le jeton
// lui-même est la capacité d'accès ; throttle pour limiter les abus.
// Préfixe /intake distinct de /demandes (routes internes) pour éviter toute
// collision d'URL entre le jeton public et l'id numérique de la demande.
Route::middleware('throttle:20,1')->prefix('intake/{token}')->name('intake.')->group(function () {
    Route::get('/', [IntakeController::class, 'show'])->name('show');
    Route::post('/ocr', [IntakeController::class, 'ocr'])->name('ocr');
    Route::post('/', [IntakeController::class, 'store'])->name('store');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Notifications (cloche)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/lue', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/tout-lire', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

    // Dossiers (CRUD + actions workflow)
    Route::resource('dossiers', DossierController::class)->parameters(['dossiers' => 'dossier:reference']);
    Route::post('/dossiers/{dossier:reference}/avancer', [DossierController::class, 'avancer'])->name('dossiers.avancer');
    Route::post('/dossiers/{dossier:reference}/generer-documents', [DossierController::class, 'genererDocuments'])->name('dossiers.generer_documents');
    Route::patch('/dossiers/{dossier:reference}/questionnaire', [DossierController::class, 'updateQuestionnaire'])->name('dossiers.questionnaire.update');
    Route::get('/dossiers/{dossier:reference}/fiche-recueil', [DossierController::class, 'telechargerFicheRecueil'])->name('dossiers.fiche_recueil');
    Route::post('/dossiers/{dossier:reference}/accord-client', [DossierController::class, 'televerserAccordClient'])->name('dossiers.accord_client.televerser');

    // Dates de signature : action à part, restreinte à l'étape Signature. Ces champs
    // étaient dans UpdateDossierRequest, donc modifiables et effaçables à toute étape.
    Route::patch('/dossiers/{dossier:reference}/signatures', [DossierController::class, 'enregistrerSignatures'])->name('dossiers.signatures');

    // Clôture : vérification pièce par pièce de l'inventaire du dossier. Remplace la
    // configuration « documents obligatoires par type d'acte » (Paramètres > Clôture,
    // supprimée) — voir InventaireClotureService.
    Route::post('/dossiers/{dossier:reference}/cloture/verifications', [ClotureController::class, 'verifier'])->name('dossiers.cloture.verifier');
    Route::delete('/dossiers/{dossier:reference}/cloture/verifications', [ClotureController::class, 'retirerVerification'])->name('dossiers.cloture.retirer_verification');
    Route::post('/dossiers/{dossier:reference}/cloture/verifications/rubrique', [ClotureController::class, 'verifierRubrique'])->name('dossiers.cloture.verifier_rubrique');

    // Parties additionnelles (personnes non liées à un rôle du questionnaire)
    Route::post('/dossiers/{dossier:reference}/parties', [PartieController::class, 'store'])->name('dossiers.parties.store');
    Route::delete('/parties/{partie}', [PartieController::class, 'destroy'])->name('parties.destroy');
    Route::post('/parties/{partie}/photo', [PartieController::class, 'uploaderPhoto'])->name('parties.photo');
    Route::post('/parties/{partie}/pieces', [PartieController::class, 'uploaderPiece'])->name('parties.pieces.store');
    Route::post('/parties/{partie}/pieces/{categorie}/televerser', [PartieController::class, 'televerserPieceRequise'])->name('parties.pieces.televerser_requise');
    // Reprise d'une pièce déjà fournie par la même personne (même `client_id`) dans un autre
    // dossier — déclarée avant la route paramétrée `{categorie}`, sinon « reprendre-tout » serait
    // pris pour une catégorie.
    Route::post('/parties/{partie}/pieces/reprendre-tout', [PartieController::class, 'reprendreTout'])->name('parties.pieces.reprendre_tout');
    Route::post('/parties/{partie}/pieces/{categorie}/reprendre', [PartieController::class, 'reprendrePiece'])->name('parties.pieces.reprendre');

    // Documents
    Route::post('/dossiers/{dossier:reference}/documents', [DocumentController::class, 'store'])->name('dossiers.documents.store');
    Route::post('/documents/{document}/update', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/download',   [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview',    [DocumentController::class, 'preview'])->name('documents.preview');
    Route::post('/documents/{document}/regenerer', [DocumentController::class, 'regenerer'])->name('documents.regenerer');
    Route::get('/documents/{document}/versions', [DocumentController::class, 'versions'])->name('documents.versions');
    Route::get('/documents/versions/{version}/telecharger', [DocumentController::class, 'telechargerVersion'])->name('documents.versions.telecharger');
    Route::post('/documents/versions/{version}/restaurer', [DocumentController::class, 'restaurerVersion'])->name('documents.versions.restaurer');
    Route::post('/documents/{document}/televerser-signe', [DocumentController::class, 'televerserSigne'])->name('documents.televerser_signe');

    // GED (vue transversale de tous les documents, tous dossiers confondus)
    Route::get('/ged', [GedController::class, 'index'])->name('ged.index');

    // Révisions
    Route::get('/revisions', [RevisionController::class, 'index'])->name('revisions.index');
    Route::get('/dossiers/{dossier:reference}/revision', [RevisionController::class, 'show'])->name('dossiers.revision');
    Route::put('/dossiers/{dossier:reference}/revision', [RevisionController::class, 'update'])->name('dossiers.revision.update');
    Route::post('/dossiers/{dossier:reference}/revision/valider', [RevisionController::class, 'valider'])->name('dossiers.revision.valider');
    Route::post('/dossiers/{dossier:reference}/revision/renvoyer', [RevisionController::class, 'renvoyer'])->name('dossiers.revision.renvoyer');

    // Formalités
    Route::get('/formalites', [FormaliteController::class, 'index'])->name('formalites.index');
    Route::get('/formalites/export.csv', [FormaliteController::class, 'exportCsv'])->name('formalites.export');
    Route::post('/dossiers/{dossier:reference}/formalites', [FormaliteController::class, 'store'])->name('dossiers.formalites.store');
    Route::patch('/formalites/{formalite}', [FormaliteController::class, 'update'])->name('formalites.update');
    Route::post('/formalites/{formalite}/deposer', [FormaliteController::class, 'deposer'])->name('formalites.deposer');
    Route::post('/formalites/{formalite}/retour', [FormaliteController::class, 'retour'])->name('formalites.retour');
    Route::get('/formalites/{formalite}/autres-retards', [FormaliteController::class, 'autresRetardsMemeOrganisme'])->name('formalites.autresRetards');
    Route::post('/formalites/pieces/{piece}/televerser', [FormaliteController::class, 'televerserPiece'])->name('formalites.pieces.televerser');
    Route::get('/formalites/pieces/{piece}/telecharger', [FormaliteController::class, 'telechargerPiece'])->name('formalites.pieces.telecharger');
    Route::delete('/formalites/{formalite}', [FormaliteController::class, 'destroy'])->name('formalites.destroy');

    // Facturation
    Route::get('/facturation', [FactureController::class, 'index'])->name('facturation.index');
    Route::post('/dossiers/{dossier:reference}/paiements', [FactureController::class, 'enregistrerPaiement'])->name('dossiers.paiements.store');
    Route::patch('/paiements/{paiement}', [FactureController::class, 'updatePaiement'])->name('paiements.update');
    Route::delete('/paiements/{paiement}', [FactureController::class, 'destroyPaiement'])->name('paiements.destroy');
    Route::post('/paiements/{paiement}/recu', [FactureController::class, 'genererRecu'])->name('paiements.recu.generer');
    Route::get('/recus/{recu}/telecharger', [FactureController::class, 'telechargerRecu'])->name('recus.telecharger');
    Route::get('/recus/{recu}/apercu', [FactureController::class, 'apercuRecu'])->name('recus.apercu');
    Route::get('/factures/{facture}/telecharger', [FactureController::class, 'telechargerPdf'])->name('factures.telecharger');
    Route::post('/factures/{facture}/lignes', [FactureController::class, 'storeLigne'])->name('factures.lignes.store');
    Route::patch('/lignes/{ligne}', [FactureController::class, 'updateLigne'])->name('lignes.update');
    Route::delete('/lignes/{ligne}', [FactureController::class, 'destroyLigne'])->name('lignes.destroy');

    // Demandes externes (générer un lien, consulter, convertir en dossier)
    Route::get('/demandes', [DemandeController::class, 'index'])->name('demandes.index');
    Route::post('/demandes', [DemandeController::class, 'store'])->name('demandes.store');
    Route::get('/demandes/{demande}', [DemandeController::class, 'show'])->name('demandes.show');
    Route::get('/demandes/{demande}/scan', [DemandeController::class, 'scan'])->name('demandes.scan');
    Route::post('/demandes/{demande}/convertir', [DemandeController::class, 'convertir'])->name('demandes.convertir');
    Route::delete('/demandes/{demande}', [DemandeController::class, 'destroy'])->name('demandes.destroy');

    // Recherche globale
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // Répertoire (Module 9)
    Route::get('/repertoire', [RepertoireController::class, 'index'])->name('repertoire.index');
    Route::get('/repertoire/autocomplete', [RepertoireController::class, 'autocomplete'])->name('repertoire.autocomplete');

    // Brouillons de l'assistant de création de dossier (saisie inachevée, propre à
    // son auteur — pas un Dossier, pour ne pas consommer de référence notariale)
    Route::post('/dossiers/brouillons', [DossierBrouillonController::class, 'store'])->name('dossiers.brouillons.store');
    Route::delete('/dossiers/brouillons/{brouillon}', [DossierBrouillonController::class, 'destroy'])->name('dossiers.brouillons.destroy');

    // Clients (recherche/création rapide depuis le questionnaire de dossier)
    Route::get('/clients/autocomplete', [ClientController::class, 'autocomplete'])->name('clients.autocomplete');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    // Correction d'une fiche : répercute l'identité sur les dossiers non clôturés
    // qui la référencent (voir ClientProjectionService).
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');

    // Sociétés (registre : recherche/création depuis l'assistant, notamment pour ouvrir
    // un dossier de modification sur une société déjà constituée par l'étude).
    // Pas de destroy : supprimer une fiche référencée casserait la projection `soc.*` des
    // dossiers qui s'en servent — une société hors périmètre est désactivée.
    // Aperçu des actes qu'une procédure produira, avant que le dossier existe — alimente le
    // récapitulatif de l'assistant, qui annonçait jusqu'ici autre chose que ce qui serait généré.
    Route::get('/types-actes/{typeActe}/actes-prevus', [TypeActeController::class, 'actesPrevus'])->name('types_actes.actes_prevus');

    Route::get('/societes/autocomplete', [SocieteController::class, 'autocomplete'])->name('societes.autocomplete');
    // Déclarée AVANT `/societes/{societe}` : sans quoi « pieces » serait pris pour un identifiant.
    Route::delete('/societes/pieces/{piece}', [SocietePieceController::class, 'destroy'])->name('societes.pieces.destroy');
    Route::get('/societes/{societe}', [SocieteController::class, 'show'])->name('societes.show');
    Route::post('/societes', [SocieteController::class, 'store'])->name('societes.store');
    Route::patch('/societes/{societe}', [SocieteController::class, 'update'])->name('societes.update');
    // Dossier constitutif d'une société que l'étude n'a pas constituée : statuts en vigueur, RCCM…
    // Rattaché à la société et non à un dossier — réutilisé par chacune de ses modifications.
    Route::post('/societes/{societe}/pieces/{categorie}', [SocietePieceController::class, 'televerser'])->name('societes.pieces.televerser');

    // Modèles d'actes
    Route::get('/modeles', [ModeleActeController::class, 'index'])->name('modeles.index');
    Route::post('/modeles', [ModeleActeController::class, 'store'])->name('modeles.store');
    Route::post('/modeles/{modele}/dupliquer', [ModeleActeController::class, 'dupliquer'])->name('modeles.dupliquer');
    Route::patch('/modeles/{modele}', [ModeleActeController::class, 'update'])->name('modeles.update');
    Route::delete('/modeles/{modele}', [ModeleActeController::class, 'destroy'])->name('modeles.destroy');

    // Modèles de courriers (lettres de transmission — étape Expédition)
    Route::post('/modeles-courriers', [ModeleCourrierController::class, 'store'])->name('modeles_courriers.store');
    Route::post('/modeles-courriers/{modeleCourrier}/dupliquer', [ModeleCourrierController::class, 'dupliquer'])->name('modeles_courriers.dupliquer');
    Route::patch('/modeles-courriers/{modeleCourrier}', [ModeleCourrierController::class, 'update'])->name('modeles_courriers.update');
    Route::delete('/modeles-courriers/{modeleCourrier}', [ModeleCourrierController::class, 'destroy'])->name('modeles_courriers.destroy');

    // Courriers
    Route::get('/courriers', [CourrierController::class, 'index'])->name('courriers.index');
    Route::post('/courriers', [CourrierController::class, 'store'])->name('courriers.store');
    Route::patch('/courriers/{courrier}', [CourrierController::class, 'update'])->name('courriers.update');
    Route::delete('/courriers/{courrier}', [CourrierController::class, 'destroy'])->name('courriers.destroy');
    Route::get('/courriers/{courrier}/download', [CourrierController::class, 'download'])->name('courriers.download');
    Route::get('/courriers/{courrier}/preview',  [CourrierController::class, 'preview'])->name('courriers.preview');
    Route::post('/dossiers/{dossier:reference}/courriers/generer', [CourrierController::class, 'genererDepuisModele'])->name('dossiers.courriers.generer');
    Route::post('/courriers/{courrier}/televerser-signe', [CourrierController::class, 'televerserSigne'])->name('courriers.televerser_signe');

    // Paramètres (Module 10 - admin only)
    Route::middleware('role:administrateur')->prefix('parametres')->name('parametres.')->group(function () {
        Route::get('/', [ParametresController::class, 'index'])->name('index');
        Route::get('/utilisateurs', [ParametresController::class, 'utilisateurs'])->name('utilisateurs');
        Route::post('/utilisateurs', [ParametresController::class, 'storeUtilisateur'])->name('utilisateurs.store');
        Route::patch('/utilisateurs/{user}', [ParametresController::class, 'updateUtilisateur'])->name('utilisateurs.update');
        Route::get('/types-actes', [ParametresController::class, 'typesActes'])->name('types_actes');
        Route::post('/types-actes', [ParametresController::class, 'storeTypeActe'])->name('types_actes.store');
        Route::patch('/types-actes/{typeActe}', [ParametresController::class, 'updateTypeActe'])->name('types_actes.update');
        // Documents attendus par procédure — configuration effective de l'étude, seedée depuis la
        // référence du CR de juillet 2026 et réinitialisable à tout moment.
        Route::patch('/types-actes/{typeActe}/documents-attendus', [ParametresController::class, 'updateDocumentsAttendus'])->name('documents_attendus.update');
        Route::post('/types-actes/{typeActe}/documents-attendus/reinitialiser', [ParametresController::class, 'reinitialiserDocumentsAttendus'])->name('documents_attendus.reinitialiser');
        Route::get('/baremes', [ParametresController::class, 'baremes'])->name('baremes');
        Route::post('/baremes', [ParametresController::class, 'storeBareme'])->name('baremes.store');
        Route::patch('/baremes/{bareme}', [ParametresController::class, 'updateBareme'])->name('baremes.update');
        Route::delete('/baremes/{bareme}', [ParametresController::class, 'destroyBareme'])->name('baremes.destroy');
        // Les routes /cloture et /cloture/bulk ont été supprimées le 2026-08-04 :
        // l'inventaire de clôture est désormais dérivé du workflow, il n'y a plus rien à
        // configurer par type d'acte (voir InventaireClotureService).
        Route::get('/apparence', [ParametresController::class, 'apparence'])->name('apparence');
        Route::post('/apparence', [ParametresController::class, 'updateApparence'])->name('apparence.update');
        Route::post('/apparence/logo', [ParametresController::class, 'uploadLogo'])->name('apparence.logo');
        Route::delete('/apparence/logo', [ParametresController::class, 'deleteLogo'])->name('apparence.logo.delete');
        Route::get('/securite', [ParametresController::class, 'securite'])->name('securite');
        Route::post('/securite', [ParametresController::class, 'updateSecurite'])->name('securite.update');
        Route::post('/defauts', [ParametresController::class, 'updateDefauts'])->name('defauts.update');
    });

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::delete('/profile/trusted-devices/{trustedDevice}', [ProfileController::class, 'revokeTrustedDevice'])->name('profile.trusted-devices.revoke');
});

require __DIR__.'/auth.php';
