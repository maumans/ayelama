<?php

use App\Models\Client;
use App\Support\Normalisation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ramène les régimes matrimoniaux des **fiches clients** dans la liste fermée.
 *
 * Le champ était en saisie libre, et la base portait quatre orthographes pour deux régimes — dont
 * « Communaté de bien », deux fautes que les modèles Word reprenaient telle quelle dans les actes.
 *
 * Cette reprise est **nécessaire et pas seulement cosmétique** : `ClientController::regles()` valide
 * désormais le régime contre la liste. Sans elle, le client portant « Communaté de bien » serait
 * devenu inenregistrable — sa fiche aurait été refusée au premier enregistrement, même pour un
 * simple changement de téléphone.
 *
 * ⚠️ **Les questionnaires ne sont pas touchés.** Ils alimentent des actes déjà produits, et un acte
 * signé porte la valeur qui y figurait ce jour-là. C'est la même distinction que pour le référentiel
 * des lieux : une fiche client est une référence vivante que l'étude tient à jour, un questionnaire
 * est un instantané. Le `select` de la modale affiche une valeur hors liste sélectionnée et
 * signalée, plutôt que de l'effacer en silence.
 */
return new class extends Migration
{
    /**
     * Orthographes **sans ambiguïté** rencontrées, sous forme comparable → valeur canonique.
     *
     * Rien n'est deviné au-delà : une valeur dont la traduction serait douteuse est laissée telle
     * quelle et journalisée. Corriger de travers le régime matrimonial d'un client serait pire que
     * de ne rien corriger.
     */
    private const CORRESPONDANCES = [
        'communate de bien'   => 'Communauté de biens',
        'communaute de bien'  => 'Communauté de biens',
        'communaute de biens' => 'Communauté de biens',
        'separation'          => 'Séparation de biens',
        'separation de bien'  => 'Séparation de biens',
        'separation de biens' => 'Séparation de biens',
    ];

    public function up(): void
    {
        $canoniques = array_map(
            fn (string $r) => Normalisation::comparable($r),
            Client::REGIMES_MATRIMONIAUX,
        );

        $intraduisibles = [];

        foreach (DB::table('clients')->whereNotNull('regime_matrimonial')->get(['id', 'regime_matrimonial']) as $client) {
            $valeur = trim((string) $client->regime_matrimonial);

            if ($valeur === '') {
                continue;
            }

            $comparable = Normalisation::comparable($valeur);

            // Déjà conforme (aux accents et à la casse près) : on réécrit tout de même la forme
            // canonique, pour que « communauté de biens » devienne « Communauté de biens ».
            $canonique = self::CORRESPONDANCES[$comparable]
                ?? (in_array($comparable, $canoniques, true)
                    ? Client::REGIMES_MATRIMONIAUX[array_search($comparable, $canoniques, true)]
                    : null);

            if ($canonique === null) {
                $intraduisibles[] = "#{$client->id} : « {$valeur} »";
                continue;
            }

            if ($canonique !== $valeur) {
                DB::table('clients')->where('id', $client->id)->update(['regime_matrimonial' => $canonique]);
            }
        }

        // Journalisé et non silencieux : ces fiches devront être corrigées à la main, et leur
        // enregistrement sera refusé d'ici là. Mieux vaut le savoir que le découvrir.
        if ($intraduisibles !== []) {
            Log::warning(
                'Régimes matrimoniaux non traduits, à corriger à la main : ' . implode(', ', $intraduisibles),
            );
        }
    }

    /**
     * Irréversible : les orthographes d'origine étaient des fautes de frappe, rien ne justifierait
     * de les restaurer. La colonne, elle, n'a pas changé de forme.
     */
    public function down(): void
    {
        //
    }
};
