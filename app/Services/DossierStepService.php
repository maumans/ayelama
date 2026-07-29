<?php

namespace App\Services;

use App\Enums\EtapeDossier;
use App\Models\Client;
use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleCourrier;
use App\Models\Revision;
use App\Models\User;
use App\Notifications\RevisionEnAttenteNotification;
use App\Notifications\SignatureClientEnAttenteNotification;
use App\Notifications\SignatureNotaireEnAttenteNotification;
use App\Services\ActesGeneratorService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DossierStepService
{
    public function avancer(Dossier $dossier, User $user): Dossier
    {
        $this->verifierPrerequis($dossier);

        $etapeSuivante = $dossier->etape->suivante();

        if (!$etapeSuivante) {
            throw ValidationException::withMessages([
                'etape' => ['Ce dossier est déjà à l\'étape finale.'],
            ]);
        }

        $ancienneEtape = $dossier->etape;
        $dossier->etape = $etapeSuivante;
        $dossier->save();

        // Créer la révision automatiquement si on arrive en révision
        if ($etapeSuivante === EtapeDossier::Revision) {
            $revision = Revision::firstOrCreate(
                ['dossier_id' => $dossier->id],
                [
                    'reviseur_id' => $dossier->reviseur_id,
                    'statut'      => \App\Enums\StatutRevision::EnAttente,
                ]
            );
            // Nouveau round (re-entry après renvoi en édition) : table rase des verdicts précédents
            if (!$revision->wasRecentlyCreated) {
                $revision->resetPoints();
            }

            foreach ($dossier->ayantsDroit() as $destinataire) {
                try {
                    $destinataire->notify(new RevisionEnAttenteNotification($dossier));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // Signature client / notaire : pas d'onglet dédié, juste une notification
        // au notaire en charge pour lui signaler que le dossier est prêt.
        if ($etapeSuivante === EtapeDossier::SignatureClient && $dossier->notaire) {
            try {
                $dossier->notaire->notify(new SignatureClientEnAttenteNotification($dossier));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($etapeSuivante === EtapeDossier::SignatureNotaire && $dossier->notaire) {
            try {
                $dossier->notaire->notify(new SignatureNotaireEnAttenteNotification($dossier));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Générer automatiquement les lettres de transmission applicables en
        // arrivant en Expédition — toutes les données du dossier sont déjà
        // définitives à ce stade, aucune raison d'attendre un clic manuel.
        if ($etapeSuivante === EtapeDossier::Expedition) {
            $this->genererLettresTransmission($dossier, $user);
        }

        // Un client ajouté pendant la création du dossier n'est qu'un prospect tant que le
        // dossier n'a pas abouti — il devient client confirmé une fois le dossier clôturé.
        if ($etapeSuivante === EtapeDossier::Cloture) {
            // pluck()->unique() plutôt que ->distinct() en SQL : Dossier::parties() applique
            // un orderBy('role') par défaut, incompatible avec DISTINCT en SQL strict (MySQL).
            $clientIds = $dossier->parties()->whereNotNull('client_id')->pluck('client_id')->unique();
            Client::whereIn('id', $clientIds)->where('statut', 'prospect')->update(['statut' => 'client']);
        }

        JournalActivite::enregistrer(
            $dossier,
            "Dossier avancé de « {$ancienneEtape->label()} » à « {$etapeSuivante->label()} »",
            'etape',
            ['de' => $ancienneEtape->value, 'vers' => $etapeSuivante->value],
            $user
        );

        return $dossier->fresh();
    }

    /**
     * Génère une fois chaque lettre de transmission applicable (celles pas
     * déjà générées, identifiées par leur nom) — même logique que
     * CourrierController::genererDepuisModele(), déclenchée automatiquement
     * plutôt que sur clic.
     */
    private function genererLettresTransmission(Dossier $dossier, User $user): void
    {
        $dossier->loadMissing('typeActe', 'questionnaire', 'courriers');

        $modeles = ModeleCourrier::with('typesActes')
            ->actif()
            ->get()
            ->filter(fn (ModeleCourrier $m) => $m->applicablePour($dossier->typeActe));

        if ($modeles->isEmpty()) {
            return;
        }

        $dejaGenerees = $dossier->courriers->where('type', 'transmission')->pluck('objet')->all();
        $generatorService = app(ActesGeneratorService::class);

        foreach ($modeles as $modele) {
            if (in_array($modele->nom, $dejaGenerees, true)) {
                continue;
            }

            $chemin = $generatorService->genererDocument($dossier, $modele->chemin_fichier, Str::slug($modele->nom));

            $annee     = now()->year;
            $count     = Courrier::whereYear('created_at', $annee)->count() + 1;
            $reference = 'COU-' . $annee . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            Courrier::create([
                'reference'      => $reference,
                'dossier_id'     => $dossier->id,
                'redacteur_id'   => $user->id,
                'destinataire'   => '',
                'objet'          => $modele->nom,
                'type'           => 'transmission',
                'statut'         => 'brouillon',
                'chemin_fichier' => $chemin,
            ]);
        }

        JournalActivite::enregistrer($dossier, 'Lettres de transmission générées automatiquement', 'expedition', [], $user);
    }

    public function reculer(Dossier $dossier, User $user, ?string $motif = null): Dossier
    {
        $etapePrecedente = $dossier->etape->precedente();

        if (!$etapePrecedente) {
            throw ValidationException::withMessages([
                'etape' => ['Ce dossier est déjà à l\'étape initiale.'],
            ]);
        }

        $ancienneEtape = $dossier->etape;
        $dossier->etape = $etapePrecedente;
        $dossier->save();

        $action = "Dossier renvoyé de « {$ancienneEtape->label()} » à « {$etapePrecedente->label()} »";
        if ($motif) {
            $action .= " — Motif : {$motif}";
        }

        JournalActivite::enregistrer($dossier, $action, 'renvoye', ['motif' => $motif], $user);

        return $dossier->fresh();
    }

    private function verifierPrerequis(Dossier $dossier): void
    {
        match ($dossier->etape) {
            EtapeDossier::Initialisation => $this->verifierInitialisation($dossier),
            EtapeDossier::Edition        => $this->verifierEdition($dossier),
            EtapeDossier::Revision       => $this->verifierRevisionValidee($dossier),
            EtapeDossier::Formalites     => $this->verifierFormalites($dossier),
            EtapeDossier::Expedition     => $this->verifierExpedition($dossier),
            default                      => null,
        };
    }

    private function verifierInitialisation(Dossier $dossier): void
    {
        $errors = [];

        if (empty(trim($dossier->objet ?? ''))) {
            $errors['objet'] = ["L'objet du dossier doit être renseigné avant de passer à l'édition."];
        }
        if (!$dossier->notaire_id) {
            $errors['notaire'] = ['Un notaire doit être assigné au dossier.'];
        }
        if (!$dossier->reviseur_id) {
            $errors['reviseur'] = ['Un certificateur doit être assigné au dossier.'];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function verifierEdition(Dossier $dossier): void
    {
        $dossier->loadMissing('documents');

        if ($dossier->documents->isEmpty()) {
            throw ValidationException::withMessages([
                'documents' => ['Au moins un document doit être ajouté avant de passer en certification.'],
            ]);
        }
    }

    private function verifierRevisionValidee(Dossier $dossier): void
    {
        if (!$dossier->revisionValidee()) {
            $statut = $dossier->revision?->statut?->value;
            $msg = match ($statut) {
                'renvoye'    => 'La certification a été renvoyée en correction. Corrigez les points signalés puis soumettez à nouveau.',
                'en_attente' => 'La certification est en attente. Elle doit être évaluée et validée par le certificateur.',
                'en_cours'   => 'La certification est en cours. Elle doit être validée avant de continuer.',
                default      => 'La certification doit être validée avant de passer aux formalités.',
            };
            throw ValidationException::withMessages(['revision' => [$msg]]);
        }
    }

    private function verifierFormalites(Dossier $dossier): void
    {
        $dossier->loadMissing('formalites');

        if ($dossier->formalites->isEmpty()) {
            return;
        }

        $blocking = $dossier->formalites->filter(fn ($f) => $f->statut?->value !== 'cloture');

        if ($blocking->isNotEmpty()) {
            $noms = $blocking->pluck('organisme')->join(', ');
            throw ValidationException::withMessages([
                'formalites' => ["Les formalités suivantes ne sont pas encore clôturées : {$noms}."],
            ]);
        }
    }

    private function verifierExpedition(Dossier $dossier): void
    {
        $dossier->loadMissing('typeActe', 'courriers', 'documents');

        $manquants = $dossier->documents->filter(fn ($d) => $d->est_requis && !$d->est_signe_cachete);
        if ($manquants->isNotEmpty()) {
            $noms = $manquants->pluck('nom')->join(', ');
            throw ValidationException::withMessages([
                'documents' => ["Documents obligatoires non encore signés/cachetés : {$noms}."],
            ]);
        }

        // Remplace l'ancienne règle générique (« au moins une lettre envoyée ») par le
        // même mécanisme granulaire que les documents : seuls les courriers
        // explicitement marqués obligatoires (Paramètres > Clôture) bloquent la
        // clôture — un type d'acte non configuré ne bloque plus rien automatiquement,
        // cohérent avec le comportement déjà accepté côté documents.
        $courriersManquants = $dossier->courriers->filter(fn ($c) => $c->est_requis && !$c->est_signe_cachete);
        if ($courriersManquants->isNotEmpty()) {
            $noms = $courriersManquants->pluck('objet')->join(', ');
            throw ValidationException::withMessages([
                'courriers' => ["Courriers obligatoires non encore signés/cachetés : {$noms}."],
            ]);
        }
    }
}
