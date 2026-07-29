<?php

namespace App\Models;

use App\Enums\StatutRevision;
use Illuminate\Database\Eloquent\Model;

class Revision extends Model
{
    protected $fillable = [
        'dossier_id', 'reviseur_id', 'statut',
        'commentaire', 'valide_at', 'renvoye_at',
    ];

    protected function casts(): array
    {
        return [
            'statut'     => StatutRevision::class,
            'valide_at'  => 'datetime',
            'renvoye_at' => 'datetime',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function reviseur()
    {
        return $this->belongsTo(User::class, 'reviseur_id');
    }

    public function points()
    {
        return $this->hasMany(RevisionPoint::class);
    }

    // Calculs
    /**
     * Points dont le document existe toujours sur le dossier — un document supprimé
     * ou régénéré laisse un RevisionPoint orphelin en base (voir DocumentController::
     * destroy()/regenerer() pour le nettoyage à la source) ; on l'exclut ici en plus
     * pour que la liste globale (Revisions/Index.jsx) reste identique à ce que la
     * fiche dossier calcule côté client à partir de ses documents actuels.
     */
    private function pointsValides()
    {
        $this->loadMissing(['points', 'dossier.documents']);
        $idsDocuments = $this->dossier->documents->pluck('id')->map(fn ($id) => (string) $id);

        // Un point périmé (document régénéré depuis, ex. questionnaire modifié) n'est
        // plus fiable — le certificateur doit le réexaminer, donc il ne compte pas
        // comme évalué même si son etat/commentaire est conservé pour affichage.
        return $this->points->whereIn('point_id', $idsDocuments)->where('perime', false);
    }

    public function nombreConformes(): int
    {
        return $this->pointsValides()->where('etat', 'ok')->count();
    }

    public function nombreNonConformes(): int
    {
        return $this->pointsValides()->where('etat', 'a_corriger')->count();
    }

    public function nombreEvalues(): int
    {
        return $this->pointsValides()->whereNotNull('etat')->count();
    }

    public function tousEvalues(): bool
    {
        $totalDocuments = $this->dossier->documents()->count();

        return $totalDocuments > 0 && $this->nombreEvalues() === $totalDocuments;
    }

    public function estValidable(): bool
    {
        return $this->tousEvalues() && $this->nombreNonConformes() === 0;
    }

    public function resetPoints(): void
    {
        // Nouveau round de certification : les points encore « à corriger » (jamais
        // régénérés depuis) et les points périmés (déjà régénérés, en attente de ce
        // nouveau round) repartent tous à zéro. Les points « ok » non périmés restent
        // — un document non retouché depuis la dernière certification n'a pas besoin
        // d'être réévalué.
        $this->points()->where(function ($q) {
            $q->where('etat', 'a_corriger')->orWhere('perime', true);
        })->delete();
        $this->update([
            'statut'     => StatutRevision::EnAttente,
            'commentaire' => null,
            'valide_at'  => null,
            'renvoye_at' => null,
        ]);
    }

    public function valider(User $reviseur): void
    {
        $this->update([
            'statut'     => StatutRevision::Valide,
            'valide_at'  => now(),
        ]);
    }

    public function renvoyer(User $reviseur, ?string $commentaire = null): void
    {
        $this->update([
            'statut'      => StatutRevision::Renvoye,
            'commentaire' => $commentaire,
            'renvoye_at'  => now(),
        ]);
    }
}
