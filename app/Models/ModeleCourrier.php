<?php

namespace App\Models;

use App\Concerns\HasTypeDocumentLabel;
use Illuminate\Database\Eloquent\Model;

class ModeleCourrier extends Model
{
    use HasTypeDocumentLabel;

    protected $table = 'modeles_courriers';

    protected $fillable = [
        'nom', 'type_document', 'chemin_fichier', 'version',
        'est_actif', 'applicable_tous', 'obligatoire_cloture', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'est_actif'           => 'boolean',
            'applicable_tous'     => 'boolean',
            'obligatoire_cloture' => 'boolean',
        ];
    }

    public function typesActes()
    {
        return $this->belongsToMany(TypeActe::class, 'modele_courrier_type_acte');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }

    /**
     * true si cette lettre s'applique au type d'acte donné — soit parce
     * qu'elle est marquée « applicable à tous », soit par liaison explicite.
     */
    public function applicablePour(?TypeActe $typeActe): bool
    {
        if (!$typeActe) {
            return false;
        }

        return $this->applicable_tous || $this->typesActes->contains('id', $typeActe->id);
    }

    /**
     * Répercute obligatoire_cloture sur les courriers déjà générés depuis ce modèle,
     * dans les dossiers pas encore clôturés — même logique que
     * ModeleActe::synchroniserDocumentsRequis(), correspondance par `objet` (le champ
     * où genererDepuisModele() range le nom du modèle) plutôt que `nom`.
     */
    public function synchroniserCourriersRequis(): int
    {
        $dossierIds = Dossier::where('etape', '!=', 'cloture')->pluck('id');

        return Courrier::whereIn('dossier_id', $dossierIds)
            ->where('objet', $this->nom)
            ->where('type', 'transmission')
            ->where('est_signe_cachete', false)
            ->update(['est_requis' => $this->obligatoire_cloture]);
    }
}
