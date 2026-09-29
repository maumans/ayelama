<?php

use App\Enums\DonneeAuRetour;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Déclare les données attendues sur les barèmes **déjà semés**.
 *
 * `BaremeSeeder::creerBaremes()` emploie `firstOrCreate` : il ne met jamais à jour un barème
 * existant — délibérément, pour ne pas écraser les tarifs que l'étude a édités depuis
 * Paramètres. Ajouter `donnees_au_retour` au seeder ne suffit donc pas : les 104 barèmes déjà
 * en base ne l'auraient jamais reçu, et la capture serait restée lettre morte sur tout dossier
 * ouvert avec la grille actuelle.
 *
 * ⚠️ **Mapping littéral, jamais déduit.** `retour_attendu` est du texte libre : en faire la
 * source d'une règle par correspondance approximative serait une contrainte inventée. Les cinq
 * chaînes ci-dessous sont celles **mesurées en base** le 2026-09-29, citées mot pour mot. Tout
 * ce qui n'est pas reconnu est journalisé et laissé tel quel — un barème que l'étude a créé
 * elle-même ne doit pas se voir attribuer une capture qu'elle n'a pas demandée.
 */
return new class extends Migration
{
    /**
     * Par **libellé** d'abord : « Frais APIP et RCCM » rapporte les trois données depuis la
     * fusion des deux anciens barèmes APIP, ce que sa chaîne `retour_attendu` d'origine
     * (« Extrait RCCM définitif ») ne dit pas encore.
     */
    private const PAR_LIBELLE = [
        'Frais APIP et RCCM' => ['rccm_numero', 'rccm_date', 'nif'],
    ];

    /** Puis par retour attendu, pour les barèmes que le libellé ne désigne pas. */
    private const PAR_RETOUR_ATTENDU = [
        'Extrait RCCM définitif'       => ['rccm_numero', 'rccm_date'],
        'NIF attribué'                 => ['nif'],
        'Quittance de paiement'        => ['quittance_numero'],
        // Deux retours qui n'apportent qu'un papier, aucune donnée à saisir. Déclarés pour que
        // la lecture de cette liste soit exhaustive, et qu'on ne se demande pas s'ils ont été
        // oubliés.
        'Attestation de transcription' => [],
        "Bordereau d'inscription"      => [],
    ];

    public function up(): void
    {
        $inconnus = [];

        foreach (DB::table('baremes')->whereNotNull('retour_attendu')->get() as $bareme) {
            $donnees = self::PAR_LIBELLE[$bareme->libelle]
                ?? self::PAR_RETOUR_ATTENDU[$bareme->retour_attendu]
                ?? null;

            if ($donnees === null) {
                $inconnus[] = $bareme->retour_attendu;
                continue;
            }

            DB::table('baremes')->where('id', $bareme->id)->update([
                'donnees_au_retour' => json_encode($donnees),
            ]);
        }

        if ($inconnus !== []) {
            Log::info('Retours attendus non reconnus par la migration des données au retour', [
                'valeurs' => array_values(array_unique($inconnus)),
            ]);
        }

        // Les formalités **déjà générées et pas encore revenues** héritent de la déclaration de
        // leur barème : sans cela, leur formulaire de retour ne demanderait rien et les dossiers
        // en cours resteraient dans l'angle mort qu'on vient de fermer.
        //
        // ⚠️ Celles qui sont **déjà revenues** ne sont pas touchées : leur retour est enregistré,
        // et leur demander rétroactivement une donnée qu'on ne leur a jamais réclamée
        // n'apporterait rien. Les 12 données perdues se rattrapent depuis la fiche société, pas
        // en réécrivant l'histoire.
        DB::table('formalites')
            ->whereNull('donnees_au_retour')
            ->where('statut', '!=', 'retour_recu')
            ->whereNotNull('bareme_id')
            ->orderBy('id')
            ->each(function ($formalite) {
                $declare = DB::table('baremes')->where('id', $formalite->bareme_id)->value('donnees_au_retour');

                if ($declare !== null) {
                    DB::table('formalites')->where('id', $formalite->id)->update([
                        'donnees_au_retour' => $declare,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('baremes')->update(['donnees_au_retour' => null]);
        DB::table('formalites')->update(['donnees_au_retour' => null]);
    }
};
