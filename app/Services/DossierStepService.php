<?php

namespace App\Services;

use App\Enums\EtapeDossier;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleCourrier;
use App\Models\Revision;
use App\Models\User;
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
            $errors['reviseur'] = ['Un réviseur doit être assigné au dossier.'];
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
                'documents' => ['Au moins un document doit être ajouté avant de passer en révision.'],
            ]);
        }
    }

    private function verifierRevisionValidee(Dossier $dossier): void
    {
        if (!$dossier->revisionValidee()) {
            $statut = $dossier->revision?->statut?->value;
            $msg = match ($statut) {
                'renvoye'    => 'La révision a été renvoyée en correction. Corrigez les points signalés puis soumettez à nouveau.',
                'en_attente' => 'La révision est en attente. Elle doit être évaluée et validée par le réviseur.',
                'en_cours'   => 'La révision est en cours. Elle doit être validée avant de continuer.',
                default      => 'La révision doit être validée avant de passer aux formalités.',
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
        $dossier->loadMissing('typeActe', 'courriers');

        $applicables = ModeleCourrier::with('typesActes')
            ->actif()
            ->get()
            ->filter(fn (ModeleCourrier $m) => $m->applicablePour($dossier->typeActe));

        if ($applicables->isEmpty()) {
            return;
        }

        $envoye = $dossier->courriers->contains(
            fn ($c) => $c->type === 'transmission' && $c->statut === 'envoye'
        );

        if (!$envoye) {
            $noms = $applicables->pluck('nom')->join(', ');
            throw ValidationException::withMessages([
                'courriers' => ["Au moins une lettre de transmission doit être générée et marquée « envoyée » avant de clôturer ce dossier (ex. : {$noms})."],
            ]);
        }
    }
}
