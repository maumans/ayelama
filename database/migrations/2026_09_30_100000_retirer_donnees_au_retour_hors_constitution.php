<?php

use App\Enums\DonneeAuRetour;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire des barèmes de modification et de dissolution les données au retour qu'ils ne peuvent
 * pas délivrer.
 *
 * ⚠️ **Un accident de semis, pas une décision.** La migration qui a introduit
 * `baremes.donnees_au_retour` appliquait une correspondance **par libellé** — « Immatriculation
 * RCCM » → `[rccm_numero, rccm_date]`, « Obtention NIF » → `[nif]`. Or ces deux libellés existent
 * aussi sur `SOC-MOD` et `SOC-DIS`, où ils désignent tout autre chose : une modification ne fait
 * pas immatriculer la société, elle fait inscrire une **déclaration modificative**.
 *
 * Mesuré le 2026-09-30 : 4 barèmes concernés, **tous inactifs**. Aucune formalité n'en est donc
 * née et aucune fiche n'a été touchée — la mine était armée, pas déclenchée. Le jour où l'étude
 * en aurait activé un, le numéro rendu par le greffe pour une modification serait entré dans
 * `societes.rccm_numero` d'une fiche encore vide, c'est-à-dire exactement l'écrasement d'identité
 * que {@see DonneeAuRetour::colonne()} refuse en donnant `null` à
 * `DeclarationModificativeNumero`.
 *
 * **Rien n'est mis à la place.** `DeclarationModificativeNumero` serait plausible, mais aucun de
 * ces barèmes n'est actif, aucune formalité de modification n'existe encore, et le dépôt tient
 * qu'une contrainte inventée est pire qu'une contrainte absente. L'étude déclarera ce qui revient
 * le jour où elle activera la ligne, depuis Paramètres → Barèmes.
 *
 * Le garde-fou, lui, est dans le code et survit à cette migration :
 * {@see App\Services\EnregistrementRetourFormalite::reserveALaConstitution()} refuse d'écrire
 * `rccm_numero` et `date_constitution` depuis un dossier qui n'est pas une constitution — la
 * donnée est captée sur la formalité et journalisée « sans destination ».
 */
return new class extends Migration
{
    /** Les seules données qu'une constitution peut délivrer, et elle seule. */
    private const RESERVEES_CONSTITUTION = [
        'rccm_numero',
        'rccm_date',
        'nif',
    ];

    private const TYPES_HORS_CONSTITUTION = ['SOC-MOD', 'SOC-DIS'];

    public function up(): void
    {
        $baremes = DB::table('baremes')
            ->join('types_actes', 'baremes.type_acte_id', '=', 'types_actes.id')
            ->whereIn('types_actes.code', self::TYPES_HORS_CONSTITUTION)
            ->whereNotNull('baremes.donnees_au_retour')
            ->select('baremes.id', 'baremes.donnees_au_retour')
            ->get();

        foreach ($baremes as $bareme) {
            $declarees = json_decode($bareme->donnees_au_retour ?? '[]', true) ?: [];
            $restantes = array_values(array_diff($declarees, self::RESERVEES_CONSTITUTION));

            if ($restantes === $declarees) {
                continue;
            }

            DB::table('baremes')->where('id', $bareme->id)->update([
                // Une liste devenue vide redevient `null` et non `[]` : la colonne est nullable,
                // et « rien de déclaré » doit se lire pareil qu'avant l'introduction du réglage.
                'donnees_au_retour' => $restantes === [] ? null : json_encode($restantes),
            ]);
        }
    }

    /**
     * Pas de retour arrière : remettre `rccm_numero` sur un barème de modification consisterait à
     * réarmer un défaut. Les barèmes concernés sont inactifs, la perte est nulle.
     */
    public function down(): void
    {
        //
    }
};
