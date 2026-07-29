<?php

namespace App\Models;

use App\Concerns\HasTypeDocumentLabel;
use Illuminate\Database\Eloquent\Model;

class ModeleActe extends Model
{
    use HasTypeDocumentLabel;

    protected $table = 'modeles_actes';

    protected $fillable = [
        'type_acte_id', 'nom', 'type_document', 'chemin_fichier',
        'version', 'est_actif', 'obligatoire_cloture', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'est_actif'           => 'boolean',
            'obligatoire_cloture' => 'boolean',
        ];
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
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
     * Répercute `obligatoire_cloture` sur les documents déjà générés depuis ce modèle,
     * dans les dossiers encore en cours (pas déjà `Clôturé` — on ne rouvre pas une
     * exigence après coup sur un dossier déjà terminé). Sans ça, `est_requis` reste figé
     * à sa valeur au moment de la génération du document, et cocher/décocher un modèle
     * ici n'a aucun effet sur les dossiers déjà créés — seuls les nouveaux en profitent.
     *
     * Correspondance document → modèle par `nom` (même convention que
     * DocumentController::regenerer()) : aucune clé étrangère directe entre les deux.
     */
    public function synchroniserDocumentsRequis(): int
    {
        $dossierIds = Dossier::where('type_acte_id', $this->type_acte_id)
            ->where('etape', '!=', 'cloture')
            ->pluck('id');

        return DocumentFichier::where('documentable_type', Dossier::class)
            ->whereIn('documentable_id', $dossierIds)
            ->where('nom', $this->nom)
            ->where('est_signe_cachete', false)
            ->update(['est_requis' => $this->obligatoire_cloture]);
    }
}
