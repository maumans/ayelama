<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rattrape les formalités restées au statut `cloture`, valeur retirée de
 * `StatutFormalite` sans migration de données.
 *
 * Conséquence observée en production : la fiche d'un dossier portant une telle
 * formalité renvoyait un **500** — `ValueError: "cloture" is not a valid backing value
 * for enum App\Enums\StatutFormalite`, levé par le cast Eloquent. 14 formalités sur 28
 * étaient concernées, sur 4 dossiers.
 *
 * `retour_recu` est la cible : c'est l'état terminal depuis la suppression de l'étape de
 * clôture par formalité (voir StatutFormalite::estTerminee() et décision #35). Une
 * formalité anciennement « clôturée » était bien une démarche achevée.
 *
 * Leçon : retirer un cas d'enum casté par Eloquent exige TOUJOURS de migrer les lignes
 * existantes. Le code peut être parfaitement cohérent et l'application planter quand même
 * — aucun test sur base neuve ne pouvait détecter ceci.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('formalites')
            ->where('statut', 'cloture')
            ->update(['statut' => 'retour_recu']);
    }

    public function down(): void
    {
        // Irréversible : `cloture` n'existe plus dans l'enum, restaurer la valeur
        // recasserait le cast. Et rien ne distingue une formalité anciennement clôturée
        // d'une autre au retour reçu.
    }
};
