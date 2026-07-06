<?php

use App\Http\Controllers\CourrierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\FormaliteController;
use App\Http\Controllers\ModeleActeController;
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

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Dossiers (CRUD + actions workflow)
    Route::resource('dossiers', DossierController::class)->parameters(['dossiers' => 'dossier:reference']);
    Route::post('/dossiers/{dossier:reference}/avancer', [DossierController::class, 'avancer'])->name('dossiers.avancer');
    Route::post('/dossiers/{dossier:reference}/generer-documents', [DossierController::class, 'genererDocuments'])->name('dossiers.generer_documents');
    Route::patch('/dossiers/{dossier:reference}/questionnaire', [DossierController::class, 'updateQuestionnaire'])->name('dossiers.questionnaire.update');

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
    Route::post('/dossiers/{dossier:reference}/formalites', [FormaliteController::class, 'store'])->name('dossiers.formalites.store');
    Route::patch('/formalites/{formalite}', [FormaliteController::class, 'update'])->name('formalites.update');
    Route::delete('/formalites/{formalite}', [FormaliteController::class, 'destroy'])->name('formalites.destroy');

    // Recherche globale
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // Répertoire (Module 9)
    Route::get('/repertoire', [RepertoireController::class, 'index'])->name('repertoire.index');
    Route::get('/repertoire/autocomplete', [RepertoireController::class, 'autocomplete'])->name('repertoire.autocomplete');

    // Modèles d'actes
    Route::get('/modeles', [ModeleActeController::class, 'index'])->name('modeles.index');
    Route::post('/modeles', [ModeleActeController::class, 'store'])->name('modeles.store');
    Route::post('/modeles/{modele}/dupliquer', [ModeleActeController::class, 'dupliquer'])->name('modeles.dupliquer');
    Route::patch('/modeles/{modele}', [ModeleActeController::class, 'update'])->name('modeles.update');
    Route::delete('/modeles/{modele}', [ModeleActeController::class, 'destroy'])->name('modeles.destroy');

    // Courriers
    Route::get('/courriers', [CourrierController::class, 'index'])->name('courriers.index');
    Route::post('/courriers', [CourrierController::class, 'store'])->name('courriers.store');
    Route::patch('/courriers/{courrier}', [CourrierController::class, 'update'])->name('courriers.update');
    Route::delete('/courriers/{courrier}', [CourrierController::class, 'destroy'])->name('courriers.destroy');

    // Paramètres (Module 10 - admin only)
    Route::middleware('role:administrateur')->prefix('parametres')->name('parametres.')->group(function () {
        Route::get('/', [ParametresController::class, 'index'])->name('index');
        Route::get('/utilisateurs', [ParametresController::class, 'utilisateurs'])->name('utilisateurs');
        Route::post('/utilisateurs', [ParametresController::class, 'storeUtilisateur'])->name('utilisateurs.store');
        Route::patch('/utilisateurs/{user}', [ParametresController::class, 'updateUtilisateur'])->name('utilisateurs.update');
        Route::get('/types-actes', [ParametresController::class, 'typesActes'])->name('types_actes');
        Route::post('/types-actes', [ParametresController::class, 'storeTypeActe'])->name('types_actes.store');
        Route::patch('/types-actes/{typeActe}', [ParametresController::class, 'updateTypeActe'])->name('types_actes.update');
        Route::get('/grilles', [ParametresController::class, 'grilles'])->name('grilles');
        Route::post('/grilles', [ParametresController::class, 'storeGrille'])->name('grilles.store');
        Route::put('/grilles/{grille}', [ParametresController::class, 'updateGrille'])->name('grilles.update');
        Route::delete('/grilles/{grille}', [ParametresController::class, 'destroyGrille'])->name('grilles.destroy');
        Route::get('/baremes', [ParametresController::class, 'baremes'])->name('baremes');
        Route::post('/baremes', [ParametresController::class, 'storeBareme'])->name('baremes.store');
        Route::patch('/baremes/{bareme}', [ParametresController::class, 'updateBareme'])->name('baremes.update');
        Route::delete('/baremes/{bareme}', [ParametresController::class, 'destroyBareme'])->name('baremes.destroy');
        Route::get('/apparence', [ParametresController::class, 'apparence'])->name('apparence');
        Route::post('/apparence', [ParametresController::class, 'updateApparence'])->name('apparence.update');
        Route::post('/apparence/logo', [ParametresController::class, 'uploadLogo'])->name('apparence.logo');
        Route::delete('/apparence/logo', [ParametresController::class, 'deleteLogo'])->name('apparence.logo.delete');
    });

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
