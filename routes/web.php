<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CourrierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\FormaliteController;
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

    // Parties additionnelles (personnes non liées à un rôle du questionnaire)
    Route::post('/dossiers/{dossier:reference}/parties', [PartieController::class, 'store'])->name('dossiers.parties.store');
    Route::delete('/parties/{partie}', [PartieController::class, 'destroy'])->name('parties.destroy');

    // Documents
    Route::post('/dossiers/{dossier:reference}/documents', [DocumentController::class, 'store'])->name('dossiers.documents.store');
    Route::post('/documents/{document}/update', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/download',   [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview',    [DocumentController::class, 'preview'])->name('documents.preview');
    Route::post('/documents/{document}/regenerer', [DocumentController::class, 'regenerer'])->name('documents.regenerer');

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
    Route::post('/paiements/{paiement}/recu', [FactureController::class, 'genererRecu'])->name('paiements.recu.generer');
    Route::get('/recus/{recu}/telecharger', [FactureController::class, 'telechargerRecu'])->name('recus.telecharger');

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

    // Clients (recherche/création rapide depuis le questionnaire de dossier)
    Route::get('/clients/autocomplete', [ClientController::class, 'autocomplete'])->name('clients.autocomplete');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');

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

    // Paramètres (Module 10 - admin only)
    Route::middleware('role:administrateur')->prefix('parametres')->name('parametres.')->group(function () {
        Route::get('/', [ParametresController::class, 'index'])->name('index');
        Route::get('/utilisateurs', [ParametresController::class, 'utilisateurs'])->name('utilisateurs');
        Route::post('/utilisateurs', [ParametresController::class, 'storeUtilisateur'])->name('utilisateurs.store');
        Route::patch('/utilisateurs/{user}', [ParametresController::class, 'updateUtilisateur'])->name('utilisateurs.update');
        Route::get('/types-actes', [ParametresController::class, 'typesActes'])->name('types_actes');
        Route::post('/types-actes', [ParametresController::class, 'storeTypeActe'])->name('types_actes.store');
        Route::patch('/types-actes/{typeActe}', [ParametresController::class, 'updateTypeActe'])->name('types_actes.update');
        Route::get('/baremes', [ParametresController::class, 'baremes'])->name('baremes');
        Route::post('/baremes', [ParametresController::class, 'storeBareme'])->name('baremes.store');
        Route::patch('/baremes/{bareme}', [ParametresController::class, 'updateBareme'])->name('baremes.update');
        Route::delete('/baremes/{bareme}', [ParametresController::class, 'destroyBareme'])->name('baremes.destroy');
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
