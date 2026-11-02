<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Une opération appliquée à une société, avec l'état avant et après — journal **immuable**.
 *
 * Addendum v1.2 § 4.1 : « aucun UPDATE ni DELETE autorisé ». La spec le tient par un déclencheur
 * PostgreSQL ; ici, par le modèle — MySQL en développement et SQLite en test n'écriraient pas le
 * même déclencheur, et une règle qui ne vaut que dans un environnement n'est pas une règle.
 *
 * ⚠️ **L'interdiction lève une exception, elle ne retourne pas `false`.** Un `save()` qui rend
 * `false` se perd dans un `if` que personne n'écrit ; une exception arrête le traitement et
 * nomme la faute. C'est la règle 2 du dépôt : rendre les défauts bruyants.
 *
 * Ce que cette table n'est pas : un doublon de `journal_activites`. Celui-ci raconte ce qui
 * s'est passé **dans un dossier** et meurt avec lui ; celle-ci dit ce qu'était **la société**
 * avant et après, et lui survit.
 */
class SocieteHistorique extends Model
{
    protected $table = 'societe_historique';

    protected $fillable = [
        'societe_id', 'dossier_id', 'operations', 'date_effet',
        'etat_avant', 'etat_apres', 'appliquee_le', 'appliquee_par',
    ];

    protected function casts(): array
    {
        return [
            'operations'   => 'array',
            'date_effet'   => 'date',
            'etat_avant'   => 'array',
            'etat_apres'   => 'array',
            'appliquee_le' => 'datetime',
        ];
    }

    /**
     * Refuse toute modification d'une ligne déjà écrite.
     *
     * Le point d'entrée unique d'Eloquent pour une mise à jour est `save()` sur un modèle qui
     * existe : le garder ici couvre `update()`, `fill()->save()`, `touch()` et les relations.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException(
                "L'historique d'une société ne se corrige pas : une opération appliquée est un "
                . 'fait daté. Pour revenir sur un effet, posez une nouvelle ligne.'
            );
        }

        return parent::save($options);
    }

    /**
     * ⚠️ **Portée réelle de l'interdiction** : elle couvre le modèle, pas le constructeur de
     * requêtes. `SocieteHistorique::where(...)->delete()` passe outre, comme il passerait outre
     * un observer. C'est un garde-fou contre l'erreur, pas contre l'intention — et c'est déjà
     * ce qui manquait. Le jour où l'étude voudra l'étanchéité, elle se posera en base, avec un
     * déclencheur par moteur.
     */
    public function delete(): bool
    {
        throw new RuntimeException(
            "L'historique d'une société ne s'efface pas — c'est ce qui le distingue du journal "
            . "d'activité, emporté par la suppression définitive de son dossier."
        );
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    /** `null` pour une radiation, constatée à la main sans dossier ouvert. */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appliquee_par');
    }

    /**
     * Les champs qui ont changé, pour l'affichage en timeline — « Capital : 10 000 000 → 25 000 000 ».
     *
     * Comparaison **lâche** (`!=`) et non stricte : les instantanés traversent JSON, où un
     * décimal casté revient en chaîne. Un `!==` aurait signalé comme modifié un capital
     * identique à lui-même.
     *
     * @return array<string, array{avant: mixed, apres: mixed}>
     */
    public function differences(): array
    {
        $avant = $this->etat_avant ?? [];
        $apres = $this->etat_apres ?? [];

        $diff = [];

        foreach ($apres as $cle => $valeur) {
            // Les listes (associés, gérants) ont leur propre rendu : une comparaison champ à
            // champ y produirait du bruit illisible.
            if (is_array($valeur)) {
                continue;
            }

            if (($avant[$cle] ?? null) != $valeur) {
                $diff[$cle] = ['avant' => $avant[$cle] ?? null, 'apres' => $valeur];
            }
        }

        return $diff;
    }
}
