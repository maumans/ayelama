<?php

use App\Models\ModeleActe;
use App\Models\ModeleActeRattachement;
use App\Models\TypeActe;
use Illuminate\Database\Migrations\Migration;

/**
 * Remet les gabarits de société en face de la forme et de la variante qu'ils servent.
 *
 * ⚠️ **Mesuré le 2026-10-30, en interrogeant `ActesGeneratorService::actesPrevus()` type par
 * type.** Quatre désordres, tous invisibles tant qu'on ne demandait pas à la génération ce
 * qu'elle produirait :
 *
 *  1. **`SOC-MOD` portait deux gabarits SARLU, actifs.** `RCCM SARLU` y servait de
 *     `declaration_rccm` sur **toutes** les variantes, et `DNSV SARLU` de `dnsv` sur une
 *     augmentation de capital. Une modification de SA, de SNC ou de GIE sortait donc des
 *     actes de SARLU. Le rattachement venait d'une reprise où un gabarit de constitution
 *     avait été recyclé faute d'équivalent de modification — le manque reste entier, mais il
 *     vaut mieux visible qu'étanché par le mauvais document.
 *  2. **`SOC-SARL` portait « Copie de RCCM SARLU »**, inactif, mais faux de la même façon.
 *  3. **La dissolution avait deux jeux concurrents.** Le jeu semé — « Acte de dissolution et
 *     liquidation » et « Insertion au JORG dissolution », rattachés à la variante
 *     `dissolution`, fichiers présents et plus richement balisés (32 et 21 balises) — était
 *     **inactif** ; un jeu téléversé à la main — « PV ASSOCIE UNIQUE » et « INSERTION », tous
 *     deux `acte_principal`, rattachés à **toutes** les variantes, 19 balises chacun — était
 *     actif. Conséquence : une **clôture de liquidation** produisait un PV de dissolution et
 *     l'insertion de dissolution, et un dossier de dissolution produisait deux actes
 *     principaux. La ligne 17 du tableau de validation signé le 2026-10-28 décrit une clôture
 *     qui ne publie rien et ne dissout rien.
 *  4. **Huit `chemin_fichier` sans leur préfixe `modeles/`.** Sans conséquence — le générateur
 *     normalise — mais deux conventions pour une colonne finissent par se payer, et l'écran
 *     d'administration, lui, ne normalise pas.
 *
 * **Désactivés, jamais supprimés** : un dossier passé référence le document produit, et il doit
 * rester explicable. Les rattachements fautifs, eux, sont bien détachés : un rattachement n'est
 * pas un document, personne ne le cite.
 *
 * Ajoute au passage le gabarit **RCCM de dissolution** que l'étude avait fourni balisé
 * (`Documents reçus/Dissolution et liquidation/RCCM_MODIF_DISSOLUTION_balises.docx`) et que le
 * dépôt n'avait jamais importé. Sa balise `${liquidateur.nom}` a été alignée sur
 * `${liquidateur.prenom_nom}`, seule forme que le résolveur connaisse.
 *
 * Idempotente.
 */
return new class extends Migration
{
    /** Rattachements à défaire : [code du type d'acte, nom du gabarit]. */
    private const RATTACHEMENTS_CROISES = [
        ['SOC-MOD',   'DNSV SARLU'],
        ['SOC-MOD',   'RCCM SARLU'],
        ['SOC-SARL',  'Copie de RCCM SARLU'],
    ];

    /** Doublons de dissolution, rattachés à toutes les variantes et typés `acte_principal`. */
    private const DOUBLONS_DISSOLUTION = ['INSERTION', 'PV ASSOCIE UNIQUE'];

    /** Le jeu semé, correctement porté par sa variante, à réactiver. */
    private const JEU_DISSOLUTION = ['Acte de dissolution et liquidation', 'Insertion au JORG dissolution'];

    public function up(): void
    {
        $this->detacherLesGabaritsCroises();
        $this->demelerLaDissolution();
        $this->importerLeRccmDeDissolution();
        $this->normaliserLesChemins();
    }

    private function detacherLesGabaritsCroises(): void
    {
        foreach (self::RATTACHEMENTS_CROISES as [$code, $nom]) {
            $typeActe = TypeActe::where('code', $code)->first();
            $modele   = ModeleActe::where('nom', $nom)->first();

            if (! $typeActe || ! $modele) {
                continue;
            }

            ModeleActeRattachement::where('type_acte_id', $typeActe->id)
                ->where('modele_acte_id', $modele->id)
                ->delete();
        }
    }

    private function demelerLaDissolution(): void
    {
        // ⚠️ `modeles_actes` n'a **pas** de colonne de description : le motif d'une
        // désactivation ne peut vivre qu'ici et au devbook. C'est une faiblesse assumée —
        // contrairement aux barèmes, où le montant part sur une facture et où la provenance a
        // donc mérité sa colonne.
        ModeleActe::whereIn('nom', self::DOUBLONS_DISSOLUTION)
            ->where('est_actif', true)
            ->update(['est_actif' => false]);

        // Le jeu semé ne se réactive que si son fichier existe vraiment : activer un gabarit
        // absent ferait produire un document vierge en se croyant configuré.
        foreach (ModeleActe::whereIn('nom', self::JEU_DISSOLUTION)->get() as $modele) {
            if ($this->fichierExiste($modele->chemin_fichier)) {
                $modele->update(['est_actif' => true]);
            }
        }
    }

    private function importerLeRccmDeDissolution(): void
    {
        $chemin = 'modeles/societe/rccm-modif-dissolution.docx';

        if (! $this->fichierExiste($chemin)) {
            return;
        }

        $modele = ModeleActe::updateOrCreate(
            ['nom' => 'Déclaration RCCM de dissolution'],
            [
                'type_document'  => 'declaration_rccm',
                'chemin_fichier' => $chemin,
                'version'        => '1.0',
                // Fourni balisé par l'étude (Documents reçus, « Dissolution et liquidation »).
                // Déclare au greffe la dissolution et la nomination du liquidateur — à ne pas
                // confondre avec la déclaration de **radiation**, qui clôt la liquidation et
                // dont aucun gabarit n'a été fourni.
                'est_actif'      => true,
            ],
        );

        $typeActe = TypeActe::where('code', 'SOC-DIS')->first();

        if ($typeActe) {
            ModeleActeRattachement::firstOrCreate([
                'modele_acte_id' => $modele->id,
                'type_acte_id'   => $typeActe->id,
                'variante'       => 'dissolution',
            ]);
        }
    }

    /**
     * Une seule écriture du chemin, `modeles/...`.
     *
     * Le générateur préfixe ce qui ne l'est pas ({@see ActesGeneratorService}) : aucune
     * génération n'était cassée. Mais l'écran d'administration teste l'existence du fichier
     * sans ce repli, et affichait donc « gabarit manquant » sur huit gabarits bien présents.
     */
    private function normaliserLesChemins(): void
    {
        foreach (ModeleActe::whereNotNull('chemin_fichier')->get() as $modele) {
            if (str_starts_with($modele->chemin_fichier, 'modeles/')) {
                continue;
            }

            $modele->update(['chemin_fichier' => 'modeles/' . $modele->chemin_fichier]);
        }
    }

    private function fichierExiste(?string $chemin): bool
    {
        if (! $chemin) {
            return false;
        }

        $relatif = str_starts_with($chemin, 'modeles/') ? $chemin : 'modeles/' . $chemin;

        return is_file(storage_path('app/private/' . $relatif));
    }

    public function down(): void
    {
        // Rien. Réactiver un doublon rattaché à toutes les variantes referait produire un acte
        // de dissolution sur une clôture de liquidation — on ne rétablit pas un défaut.
    }
};
