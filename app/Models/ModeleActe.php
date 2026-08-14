<?php

namespace App\Models;

use App\Concerns\HasTypeDocumentLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ModeleActe extends Model
{
    use HasTypeDocumentLabel;

    protected $table = 'modeles_actes';

    protected $fillable = [
        'nom', 'type_document', 'chemin_fichier',
        'version', 'est_actif', 'applicable_tous', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'est_actif'           => 'boolean',
            'applicable_tous'     => 'boolean',
        ];
    }

    /**
     * Type d'acte **d'origine** du modèle — affichage, groupage, seeders.
     *
     * ⚠️ Ce n'est **pas** ce qui décide de l'applicabilité : un modèle peut servir plusieurs types
     * depuis le 2026-08-11. Voir {@see applicablePour()}, seule source de vérité.
     */
    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    /**
     * Procédures que ce gabarit sert — type d'acte, éventuellement restreint à une variante.
     *
     * Les statuts, la DNSV et le RCCM d'une SARLU servent aussi à une modification de statuts : les
     * rattacher à un seul type obligeait à recharger les mêmes fichiers, puis à maintenir les copies
     * en parallèle. Et au sein d'un même type, tout ne se répète pas — un acte de cession ne sert
     * qu'aux cessions de parts, d'où la **variante** portée par le rattachement.
     */
    public function rattachements()
    {
        return $this->hasMany(ModeleActeRattachement::class);
    }

    /** Types d'actes servis, sans considération de variante — pour l'affichage en liste. */
    public function typesActes()
    {
        return $this->belongsToMany(TypeActe::class, 'modele_acte_rattachements');
    }

    /**
     * Rôles que ce gabarit remplit (`type_document`).
     *
     * Le vocabulaire diffère entre procédures pour un même document : les statuts sont
     * `acte_principal` en création et `statuts_maj` en modification, le RCCM `rccm` puis
     * `declaration_rccm`. Un modèle ne portant qu'un rôle, partager le gabarit ne suffisait pas —
     * constaté sur le RCCM, dont le partage n'avait rien produit. Un gabarit de statuts peut
     * désormais cocher les deux rôles et servir les deux procédures.
     *
     * `type_document` **reste** le rôle principal : affichage, tri, seeders.
     */
    /**
     * @return array<int, string> Les rôles remplis.
     *
     * Mémoïsé : la vue Processus interroge chaque gabarit pour chaque variante, et une requête par
     * appel aurait produit un N+1 sur un écran de configuration.
     */
    public function rolesRemplis(): array
    {
        if ($this->rolesMemo !== null) {
            return $this->rolesMemo;
        }

        $declares = DB::table('modele_acte_roles')
            ->where('modele_acte_id', $this->id)
            ->pluck('type_document')
            ->all();

        // `type_document` en repli : un modèle créé par un appel qui ignore les rôles (seeder,
        // import) doit rester retenu par la génération.
        return $this->rolesMemo = ($declares !== [] ? $declares : array_filter([$this->type_document]));
    }

    /** @var array<int, string>|null */
    private ?array $rolesMemo = null;

    /** Remplace les rôles de ce gabarit. Le premier devient le rôle principal (`type_document`). */
    public function definirRoles(array $typesDocument): void
    {
        $roles = array_values(array_unique(array_filter($typesDocument)));

        DB::table('modele_acte_roles')->where('modele_acte_id', $this->id)->delete();

        if ($roles === []) {
            $this->rolesMemo = null;

            return;
        }

        DB::table('modele_acte_roles')->insert(
            array_map(fn (string $r) => ['modele_acte_id' => $this->id, 'type_document' => $r], $roles),
        );

        if (!in_array($this->type_document, $roles, true)) {
            $this->update(['type_document' => $roles[0]]);
        }

        $this->rolesMemo = $roles;
    }

    public function remplitRole(?string $typeDocument): bool
    {
        return $typeDocument !== null && in_array($typeDocument, $this->rolesRemplis(), true);
    }

    /**
     * Ce gabarit sert-il cette procédure ?
     *
     * « Applicable à tous » couvre tout ; sinon il faut un rattachement au type d'acte dont la
     * variante est libre (`null`) ou égale à celle demandée.
     */
    public function applicablePour(?TypeActe $typeActe, ?string $variante = null): bool
    {
        if (!$typeActe) {
            return false;
        }

        if ($this->applicable_tous) {
            return true;
        }

        return $this->rattachements
            ->where('type_acte_id', $typeActe->id)
            ->contains(fn (ModeleActeRattachement $r) => $r->couvre($variante));
    }

    /**
     * Modèles rattachés à ce type d'acte — filtrage large, en base.
     *
     * La variante n'est **pas** filtrée ici : elle l'est en mémoire par `applicablePour()`, pour que
     * la même règle serve la génération et l'écran de configuration sans être écrite deux fois.
     */
    public function scopePourTypeActe($query, ?int $typeActeId)
    {
        return $query->where(fn ($q) => $q
            ->where('applicable_tous', true)
            ->orWhereHas('rattachements', fn ($qq) => $qq->where('type_acte_id', $typeActeId)));
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }

}
