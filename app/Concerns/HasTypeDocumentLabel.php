<?php

namespace App\Concerns;

trait HasTypeDocumentLabel
{
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
