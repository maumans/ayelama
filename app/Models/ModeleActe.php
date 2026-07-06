<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModeleActe extends Model
{
    protected $table = 'modeles_actes';

    protected $fillable = [
        'type_acte_id', 'nom', 'type_document', 'chemin_fichier',
        'version', 'est_actif', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'est_actif' => 'boolean',
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

    public function typeDocumentLabel(): string
    {
        return match ($this->type_document) {
            'acte_principal' => 'Acte principal',
            'page_garde'     => 'Page de garde',
            'attestation'    => 'Attestation',
            'declaration'    => 'Déclaration',
            'dnsv'           => 'DNSV',
            'insertion'      => 'Insertion au JORG',
            'rccm'           => 'RCCM',
            'note_frais'     => 'Note de frais',
            'bordereau'      => 'Bordereau / Tableau',
            'annexe'         => 'Annexe',
            'procedure'      => 'Procédure',
            'lettre'         => 'Lettre / Transmission',
            'recepisse'      => 'Récépissé',
            default          => $this->type_document ?? '—',
        };
    }
}
