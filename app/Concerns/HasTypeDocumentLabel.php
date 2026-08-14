<?php

namespace App\Concerns;

trait HasTypeDocumentLabel
{
    /**
     * Types de document d'un modèle — **seule référence** du projet.
     *
     * Cette liste était écrite en dur trois fois : la règle de validation de
     * `ModeleActeController::store()`, celle de `update()`, et le `match` des libellés ci-dessous.
     * Résultat constaté le 2026-08-11 : les quatre slugs de la modification statutaire
     * (`acte_cession`, `pv_modification`, `statuts_maj`, `declaration_rccm`) avaient été ajoutés au
     * seeder mais **oubliés dans les deux règles** — charger ou modifier un modèle de modification
     * partait donc en 422, et l'étude ne pouvait pas fournir son gabarit de procès-verbal.
     *
     * Une seule table : ajouter un type de document ne doit plus se faire à trois endroits.
     */
    public const TYPES_DOCUMENT = [
        'acte_principal'   => 'Acte principal',
        'page_garde'       => 'Page de garde',
        'attestation'      => 'Attestation',
        'declaration'      => 'Déclaration',
        'dnsv'             => 'DNSV',
        'insertion'        => 'Insertion au JORG',
        'rccm'             => 'RCCM',
        // Modification statutaire (CR de juillet 2026, règle 9) — voir
        // App\Enums\TypeModificationStatutaire::documentsRequis().
        'acte_cession'     => 'Acte de cession de parts',
        'pv_modification'  => "Procès-verbal de l'assemblée",
        'statuts_maj'      => 'Statuts mis à jour',
        'declaration_rccm' => 'Déclaration de modification RCCM',
        'note_frais'       => 'Note de frais',
        'bordereau'        => 'Bordereau / Tableau',
        'annexe'           => 'Annexe',
        'procedure'        => 'Procédure',
        'lettre'           => 'Lettre / Transmission',
        'recepisse'        => 'Récépissé',
    ];

    /**
     * Colonne portant le slug du type de document.
     *
     * Une méthode et non une propriété : PHP refuse qu'une classe redéclare une propriété de trait
     * avec une valeur par défaut différente (« definition differs and is considered incompatible »).
     *
     * `ModeleActe` et `ModeleCourrier` la nomment `type_document` ; `DocumentFichier` la nomme
     * `categorie` — héritage de l'unification GED du 2026-07-24, où un document produit et une pièce
     * justificative ont été réunis dans la même table. Un modèle qui diffère surcharge cette
     * propriété, plutôt que de redéclarer le vocabulaire de son côté.
     */
    protected function champTypeDocument(): string
    {
        return 'type_document';
    }

    public function typeDocumentLabel(): string
    {
        $slug = $this->{$this->champTypeDocument()} ?? null;

        // Repli sur le slug lui-même et non sur une chaîne vide : un type non répertorié doit rester
        // lisible à l'écran — c'est ce qui a permis de repérer que `statuts_maj` manquait aux tables
        // recopiées côté frontend.
        return self::TYPES_DOCUMENT[$slug] ?? ($slug ?? '—');
    }

    /** Règle de validation `in:` correspondante. */
    public static function reglesTypeDocument(): string
    {
        return 'in:' . implode(',', array_keys(self::TYPES_DOCUMENT));
    }

    /** @return array<int, array{value: string, label: string}> pour les listes déroulantes. */
    public static function typesDocumentOptions(): array
    {
        return collect(self::TYPES_DOCUMENT)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
