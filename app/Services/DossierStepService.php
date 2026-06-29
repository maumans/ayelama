<?php

namespace App\Services;

use App\Enums\EtapeDossier;
use App\Models\Dossier;
use App\Models\JournalActivite;
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
            Revision::firstOrCreate(
                ['dossier_id' => $dossier->id],
                [
                    'reviseur_id' => $dossier->reviseur_id,
                    'statut'      => \App\Enums\StatutRevision::EnAttente,
                ]
            );
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
            EtapeDossier::Initialisation   => $this->verifierInitialisation($dossier),
            EtapeDossier::Edition          => $this->verifierEdition($dossier),
            EtapeDossier::Revision         => $this->verifierRevisionValidee($dossier),
            EtapeDossier::SignatureClient  => $this->verifierSignatureClient($dossier),
            EtapeDossier::SignatureNotaire => $this->verifierSignatureNotaire($dossier),
            EtapeDossier::Formalites       => $this->verifierFormalites($dossier),
            default                        => null,
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
        $errors = [];

        if ($dossier->documents->isEmpty()) {
            $errors['documents'] = ['Au moins un document doit être ajouté avant de passer en révision.'];
        } elseif ($dossier->documents->contains('statut', 'a_editer')) {
            $noms = $dossier->documents->where('statut', 'a_editer')->pluck('nom')->join(', ');
            $errors['documents'] = ["Les documents suivants ne sont pas encore édités : {$noms}."];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
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
                default      => 'La révision doit être validée avant de passer à la signature client.',
            };
            throw ValidationException::withMessages(['revision' => [$msg]]);
        }
    }

    private function verifierSignatureClient(Dossier $dossier): void
    {
        $dossier->loadMissing('documents');

        $blocking = $dossier->documents
            ->where('signature_client_requise', true)
            ->filter(fn ($d) => !in_array($d->statut, ['signe_client', 'signe_notaire']));

        if ($blocking->isNotEmpty()) {
            $noms = $blocking->pluck('nom')->join(', ');
            throw ValidationException::withMessages([
                'documents' => ["Les documents suivants attendent la signature client : {$noms}."],
            ]);
        }
    }

    private function verifierSignatureNotaire(Dossier $dossier): void
    {
        $dossier->loadMissing('documents');

        $blocking = $dossier->documents->filter(fn ($d) => $d->statut !== 'signe_notaire');

        if ($blocking->isNotEmpty()) {
            $noms = $blocking->pluck('nom')->join(', ');
            throw ValidationException::withMessages([
                'documents' => ["Les documents suivants n'ont pas encore la signature du notaire : {$noms}."],
            ]);
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
}
