<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'type', 'statut',
        // Personne physique
        // `prenom_nom` reste fillable sans être une colonne : le mutateur ci-dessous le
        // découpe en nom_famille/prenoms. Indispensable pour ne pas casser les appels
        // existants (intake public, imports, modèles Word) — voir la migration
        // separer_nom_famille_et_prenoms_sur_clients.
        'civilite', 'nom_famille', 'prenoms', 'prenom_nom', 'ne_a', 'date_naissance',
        'nationalite', 'piece_type', 'piece_numero',
        'piece_delivree_le', 'piece_delivree_a', 'piece_expire_le',
        'situation_matrimoniale', 'regime_matrimonial',
        // Personne morale
        'denomination', 'forme', 'rccm', 'representant_legal', 'representant_qualite',
        // Communs
        'demeurant_ville', 'quartier', 'commune', 'pays',
        'telephone', 'email', 'siege',
    ];

    /**
     * `prenom_nom` n'est plus une colonne : sans cet `$appends`, il disparaîtrait des
     * réponses JSON (autocomplétion du répertoire, retour de création de fiche) et des
     * props Inertia qui l'affichent encore.
     */
    protected $appends = ['prenom_nom'];

    protected function casts(): array
    {
        return [
            'date_naissance'     => 'date',
            'piece_delivree_le'  => 'date',
            'piece_expire_le'    => 'date',
        ];
    }

    public function parties()
    {
        return $this->hasMany(Partie::class);
    }

    public function scopeProspects($query)
    {
        return $query->where('statut', 'prospect');
    }

    public function scopeClientsConfirmes($query)
    {
        return $query->where('statut', 'client');
    }

    public function estPersonnePhysique(): bool
    {
        return $this->type === 'physique';
    }

    /**
     * La pièce d'identité est-elle expirée ?
     *
     * **Avertissement, jamais blocage** (décision du 2026-08-12) : l'étude doit pouvoir consigner la
     * situation réelle du client — constater qu'une pièce est périmée fait partie du travail — puis
     * lui demander un renouvellement. La cohérence des dates entre elles, en revanche, est bloquante
     * (voir `ClientController::regles()`) : c'est une saisie impossible, pas un fait à consigner.
     */
    public function pieceExpiree(): bool
    {
        return $this->piece_expire_le !== null
            && $this->piece_expire_le->isPast();
    }

    /**
     * Ce qui mérite d'être signalé sur cette fiche sans en empêcher l'enregistrement.
     *
     * @return array<int, string>
     */
    public function avertissements(): array
    {
        $avertissements = [];

        if ($this->pieceExpiree()) {
            $avertissements[] = sprintf(
                "Pièce d'identité expirée depuis le %s — à renouveler avant signature.",
                $this->piece_expire_le->format('d/m/Y'),
            );
        }

        return $avertissements;
    }

    public function estProspect(): bool
    {
        return $this->statut === 'prospect';
    }

    public function estClient(): bool
    {
        return $this->statut === 'client';
    }

    public function nomComplet(): string
    {
        return $this->type === 'physique'
            ? trim(($this->civilite ? $this->civilite . ' ' : '') . $this->prenom_nom)
            : $this->denomination ?? '';
    }

    /**
     * Nom de famille en capitales — règle 4 du CR de juillet 2026.
     *
     * Exposé aux modèles Word via `${pp.nom_famille}`. Le **gras** exigé par la même règle
     * n'est pas programmable : PhpWord hérite du formatage du placeholder, c'est donc une
     * consigne de mise en page des modèles (voir dictionnaire_balises.md).
     */
    public function nomFamilleMajuscule(): string
    {
        return mb_strtoupper((string) $this->nom_famille);
    }

    /**
     * Identité composée « NOM Prénoms », nom de famille en capitales.
     *
     * Ordre voulu par l'usage notarial : le nom de famille d'abord, en capitales, pour
     * qu'il se distingue immédiatement des prénoms dans un acte.
     */
    public function identiteNotariale(): string
    {
        return trim($this->nomFamilleMajuscule() . ' ' . (string) $this->prenoms);
    }

    /**
     * `prenom_nom` — champ historique, conservé en lecture/écriture alors que la colonne
     * n'existe plus.
     *
     * **Accessor** : les 63 modèles Word non normalisés référencent `${pp.prenom_nom}`, et
     * la projection d'identité comme le répertoire l'affichent encore. Restitue
     * « Prénoms Nom », l'ordre de saisie d'origine.
     *
     * **Mutateur** : un appel qui écrit `prenom_nom` (intake public, import, ancien
     * formulaire) est découpé plutôt que rejeté — même règle que la migration, dernier mot
     * = nom de famille.
     */
    /** Accessors correspondants — ClientProjectionService lit des attributs, pas des méthodes. */
    public function getNomFamilleMajusculeAttribute(): string
    {
        return $this->nomFamilleMajuscule();
    }

    public function getIdentiteNotarialeAttribute(): string
    {
        return $this->identiteNotariale();
    }

    public function getPrenomNomAttribute(): string
    {
        return trim(((string) $this->prenoms) . ' ' . ((string) $this->nom_famille));
    }

    public function setPrenomNomAttribute(?string $valeur): void
    {
        $mots = preg_split('/\s+/u', trim((string) $valeur), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (!$mots) {
            $this->attributes['nom_famille'] = null;
            $this->attributes['prenoms']     = null;
            return;
        }

        if (count($mots) === 1) {
            $this->attributes['nom_famille'] = $mots[0];
            $this->attributes['prenoms']     = '';
            return;
        }

        $this->attributes['nom_famille'] = array_pop($mots);
        $this->attributes['prenoms']     = implode(' ', $mots);
    }
}
