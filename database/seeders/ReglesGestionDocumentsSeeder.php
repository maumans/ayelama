<?php

namespace Database\Seeders;

use App\Enums\TypeModificationStatutaire;
use App\Models\DocumentAttendu;
use App\Models\TypeActe;
use App\Support\VariantesTypeActe;
use Illuminate\Database\Seeder;

/**
 * Documents exigés par procédure — **référence légale portée en configuration**.
 *
 * La règle vient du CR de juillet 2026 (`Regles_Gestion_Plateforme_Notariale.docx`, tableau 9) et
 * reste écrite dans {@see TypeModificationStatutaire::documentsRequis()} : c'est la source datée,
 * traçable, que le code applique par défaut. Ce seeder en dépose une copie dans
 * `documents_attendus`, que l'étude peut ensuite ajuster depuis Paramètres > Types d'actes.
 *
 * **Idempotent et relançable** : c'est aussi le bouton « Réinitialiser à la référence » de l'écran de
 * configuration. Relancer efface les écarts et restaure exactement la règle écrite — indispensable,
 * puisqu'une configuration erronée produirait un dossier incomplet au greffe.
 *
 * Séparé de `BaremeSeeder` et de `ModeleActeSeeder` (données de démonstration) pour la même raison
 * que `ReglesGestionBaremeSeeder` : ces lignes ont une source datée, pas un caractère d'exemple.
 */
class ReglesGestionDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TypeActe::whereIn('code', array_keys(self::procedures()))->get() as $typeActe) {
            $this->semerPour($typeActe);
        }
    }

    /**
     * Réinitialise une procédure — ou toutes les variantes d'un type d'acte.
     *
     * Appelé par le seeder et par l'action « Réinitialiser à la référence » du contrôleur, pour que
     * les deux ne puissent pas diverger.
     */
    public static function reinitialiser(TypeActe $typeActe, ?string $variante = null): void
    {
        $reference = DocumentAttendu::reference($typeActe->code, $variante);

        if ($reference === null) {
            return;
        }

        DocumentAttendu::where('type_acte_id', $typeActe->id)
            ->where('variante', $variante)
            ->delete();

        $ordre = 0;
        foreach ($reference as $slug => $label) {
            DocumentAttendu::create([
                'type_acte_id'  => $typeActe->id,
                'variante'      => $variante,
                'type_document' => $slug,
                'obligatoire'   => true,
                'ordre'         => $ordre++,
            ]);
        }
    }

    private function semerPour(TypeActe $typeActe): void
    {
        foreach (VariantesTypeActe::pour($typeActe->code) as $variante) {
            self::reinitialiser($typeActe, $variante->valeur());
        }
    }

    /**
     * Types d'actes régis par une liste écrite.
     *
     * Volontairement limité : les créations, ventes, baux et hypothèques n'ont **pas** de liste
     * imposée — leurs actes sont ceux de leurs gabarits actifs. Leur en inventer une reproduirait
     * l'approche « documents obligatoires configurés par type d'acte », abandonnée pour la clôture le
     * 2026-08-04 parce qu'elle dupliquait une vérité déjà connue et pouvait la contredire.
     *
     * @return array<string, class-string>
     */
    private static function procedures(): array
    {
        return ['SOC-MOD' => TypeModificationStatutaire::class];
    }
}
