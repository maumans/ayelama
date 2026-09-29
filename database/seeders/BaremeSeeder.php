<?php

namespace Database\Seeders;

use App\Models\Bareme;
use App\Enums\FormeSociete;
use App\Models\TypeActe;
use Illuminate\Database\Seeder;

/**
 * Seeder des barèmes officiels de l'Office Notarial Ayelama BAH.
 * 
 * Source : Section D du document "Analyse_et_Prompt_Generation_Modeles_Ayelema.md"
 * Cadre juridique : OHADA — Guinée, monnaie GNF.
 *
 * ⚠️ Ces taux sont PARAMÉTRABLES en base via l'interface d'administration.
 *    Ce seeder sert uniquement de pré-remplissage initial.
 */
class BaremeSeeder extends Seeder
{
    public function run(): void
    {
        // ═════════════════════════════════════════════════════════════════
        // VENTE D'IMMEUBLE
        // ═════════════════════════════════════════════════════════════════
        $ventesTypes = TypeActe::where('categorie', 'vente')->pluck('id');

        foreach ($ventesTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires du notaire',
                    'taux'         => 5.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '5% du prix de vente',
                    'ordre'        => 1,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => "Droits d'enregistrement",
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du prix de vente',
                    'ordre'        => 2,
                    // Génère aussi la formalité correspondante (ex-FormaliteTypeSeeder VTE-IMM).
                    'genere_formalite' => true,
                    'type_impot'       => 'droits_enregistrement',
                    'delai_heures'     => 120,
                    'pieces_requises'  => ['Acte de vente signé'],
                ],
                [
                    'organisme'    => 'Conservation',
                    'libelle'      => 'Frais de mutation Conservation Foncière',
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du prix de vente',
                    'ordre'        => 3,
                    'genere_formalite' => true,
                    'retour_attendu'   => 'Titre foncier mis à jour',
                    'delai_heures'     => 240,
                    'pieces_requises'  => ['Titre foncier original', 'Certificat de situation juridique'],
                ],
                [
                    'organisme'    => 'Conservation',
                    'libelle'      => 'Frais de virement',
                    'montant_fixe' => 500000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Frais fixes de virement — 500 000 GNF',
                    'ordre'        => 4,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Timbres fiscaux & rôles',
                    'montant_fixe' => 50000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Variable selon nombre de pages',
                    'ordre'        => 5,
                ],
                [
                    'organisme'    => 'APIP',
                    'libelle'      => 'Enregistrement APIP',
                    'montant_fixe' => 150000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Frais fixes d\'enregistrement APIP',
                    'ordre'        => 6,
                    'genere_formalite' => true,
                    'delai_heures'     => 72,
                    'pieces_requises'  => ['Copie CNI vendeur', 'Copie CNI acquéreur', 'Titre foncier original'],
                ],
            ]);
        }

        // Barème Plus-Value (ajouté séparément si un type "plus_value" existe)
        // Sinon on le met sur les types de vente existants
        foreach ($ventesTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Taxe sur plus-value immobilière',
                    'taux'         => 15.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '15% du montant de la plus-value (à calculer séparément)',
                    'ordre'        => 10,
                    'actif'        => false, // désactivé par défaut, applicable si plus-value
                ],
            ]);
        }

        // ═════════════════════════════════════════════════════════════════
        // BAUX (habitation, professionnel, à construction)
        // ═════════════════════════════════════════════════════════════════
        $bauxTypes = TypeActe::where('categorie', 'bail')->pluck('id');

        foreach ($bauxTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires du notaire',
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du montant total des loyers sur la durée du bail',
                    'ordre'        => 1,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => "Droits d'enregistrement",
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du montant total des loyers sur la durée du bail',
                    'ordre'        => 2,
                ],
            ]);
        }

        // ═════════════════════════════════════════════════════════════════
        // CONSTITUTION DE SOCIÉTÉ (SA, SARL, SARLU, SAS, SASU, SNC, GIE)
        // ═════════════════════════════════════════════════════════════════
        //
        // ⚠️ Filtré sur les **constitutions** depuis le 2026-09-28. `categorie = societe`
        // ramenait aussi `SOC-MOD` et `SOC-DIS`, si bien qu'un dossier de dissolution héritait
        // de toute la grille de création : une « Immatriculation RCCM », une « Obtention NIF »,
        // des droits d'enregistrement « des Statuts et de la DNSV » et une insertion JAL
        // décrite « avis de **constitution** ». Facturer l'immatriculation d'une société qu'on
        // radie est faux, et c'est ce que la base contenait (mesuré : 7 barèmes actifs sur
        // SOC-DIS, tous hérités de la constitution).
        //
        // Le prédicat n'est pas inventé pour l'occasion : `depuisCodeTypeActe()` rend une forme
        // pour les sept constitutions et `null` pour SOC-MOD comme pour SOC-DIS — c'est
        // exactement la distinction voulue, et elle est déjà la référence ailleurs.
        //
        // **Aucun barème de dissolution n'est créé en remplacement.** Le compte rendu de
        // juillet 2026 n'en donne aucun, et inventer un montant le ferait figurer sur une
        // facture d'étude. Conséquence assumée et visible : un dossier SOC-DIS aura une facture
        // presque vide et aucune formalité automatique — corrigible en quelques lignes ici le
        // jour où l'étude communiquera sa grille.
        $societeTypes = TypeActe::where('categorie', 'societe')
            ->get()
            ->filter(fn (TypeActe $t) => FormeSociete::depuisCodeTypeActe($t->code) !== null)
            ->pluck('id');

        foreach ($societeTypes as $typeActeId) {
            $baremes = $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires forfaitaires',
                    'montant_fixe' => 4500000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Forfait honoraires notaire (variable selon capital, à ajuster)',
                    'ordre'        => 1,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Droits d\'enregistrement des Statuts et de la DNSV',
                    'donnees_au_retour' => ['quittance_numero'],
                    'montant_fixe' => 0,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => "Droits d'enregistrement des statuts et de la DNSV",
                    'ordre'        => 2,
                    'genere_formalite' => true,
                    'type_impot'       => 'droits_enregistrement',
                    'delai_heures'     => 24,
                    'pieces_requises'  => ['Statuts signés', "Formulaire d'enregistrement"],
                ],
                [
                    'organisme'    => 'APIP',
                    'libelle'      => 'Frais APIP et RCCM',
                    'montant_fixe' => 490000,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Frais APIP et RCCM',
                    'ordre'        => 3,
                    'genere_formalite' => true,
                    'retour_attendu'   => 'Extrait RCCM définitif et attestation NIF',
                    // L'APIP délivre les deux en une démarche depuis la fusion des deux
                    // anciens barèmes : le formulaire de retour réclame donc les trois
                    // données, et les porte à la fiche société. Voir App\Enums\DonneeAuRetour.
                    'donnees_au_retour' => ['rccm_numero', 'rccm_date', 'nif'],
                    'delai_heures'     => 72,
                    'pieces_requises'  => ['Statuts signés', 'Formulaire APIP', 'Copie CNI gérant'],
                ],
                [
                    'organisme'    => 'Autre',
                    'libelle'      => 'Insertion JAL (Journal Annonces Légales)',
                    'montant_fixe' => 250000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Publication de l\'avis de constitution dans le JAL',
                    'ordre'        => 5,
                    // `societes.jal_journal` existe depuis juin 2026 sans écrivain ni lecteur :
                    // c'est par ici qu'elle prend enfin un sens.
                    'genere_formalite'  => true,
                    'retour_attendu'    => 'Justificatif de parution au journal',
                    'donnees_au_retour' => ['jal_journal', 'jal_date_parution'],
                    'delai_heures'      => 168,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Timbres & rôles',
                    'montant_fixe' => 50000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Timbres fiscaux et rôles selon nombre de pages',
                    'ordre'        => 6,
                ],
            ]);

            // Le dépôt des statuts au Greffe ne peut se faire qu'après réception
            // de l'extrait RCCM définitif (retour de la démarche APIP correspondante).
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Greffe',
                    'libelle'      => 'Dépôt statuts au greffe',
                    'montant_fixe' => 100000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Dépôt des statuts définitifs auprès du greffe du tribunal de commerce',
                    'ordre'        => 7,
                    'genere_formalite'    => true,
                    'donnees_au_retour'   => ['depot_greffe_numero'],
                    'depend_de_bareme_id' => $baremes['Frais APIP et RCCM']->id,
                    'delai_heures'        => 48,
                    'pieces_requises'     => ['Extrait RCCM définitif'],
                ],
            ]);
        }

        $this->desactiverLibellesRetires();
        $this->desactiverGrilleConstitutionHorsPerimetre();

        // ═════════════════════════════════════════════════════════════════
        // HYPOTHÈQUE
        // ═════════════════════════════════════════════════════════════════
        $hypoTypes = TypeActe::where('categorie', 'hypotheque')->pluck('id');

        foreach ($hypoTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Conservation',
                    'libelle'      => 'Conservation foncière',
                    'taux'         => 1.5000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '1,5% du montant du crédit',
                    'ordre'        => 1,
                    // Génère aussi la formalité correspondante (ex-FormaliteTypeSeeder HYP-CON).
                    'genere_formalite' => true,
                    'delai_heures'     => 168,
                    'pieces_requises'  => ['Titre foncier', 'Contrat de prêt'],
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Impôts hypothécaires',
                    'taux'         => 0.1000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '0,10% du montant du crédit',
                    'ordre'        => 2,
                    'genere_formalite' => true,
                    'delai_heures'     => 120,
                ],
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires du notaire',
                    'montant_fixe' => 3000000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Forfait honoraires hypothèque (à ajuster selon le montant)',
                    'ordre'        => 3,
                ],
            ]);
        }

        $this->command->info('✅ Barèmes initiaux insérés avec succès.');
    }

    /**
     * Désactive la grille de **constitution** posée à tort sur les types d'acte de société qui
     * ne constituent rien — `SOC-MOD` et `SOC-DIS`.
     *
     * La boucle ci-dessus est désormais filtrée, mais filtrer la création ne nettoie pas ce qui
     * a déjà été semé : mesuré le 2026-09-28, un dossier de dissolution héritait de sept
     * barèmes de création, dont une « Immatriculation RCCM » et une « Obtention NIF » —
     * facturer l'immatriculation d'une société qu'on radie.
     *
     * `SOC-MOD` est traité au même titre : le défaut n'a jamais été propre à la dissolution.
     * Ses propres tarifs lui sont réappliqués par {@see ReglesGestionBaremeSeeder}, qui tourne
     * après celui-ci — les libellés qu'il pose sont donc exclus ici, sans quoi les deux
     * seeders se contrediraient à chaque exécution.
     *
     * **Désactivés, jamais supprimés**, comme partout ailleurs : une facture émise les
     * référence, et son montant doit rester explicable. Idempotent.
     */
    private function desactiverGrilleConstitutionHorsPerimetre(): void
    {
        $idsHorsPerimetre = TypeActe::where('categorie', 'societe')
            ->get()
            ->filter(fn (TypeActe $t) => FormeSociete::depuisCodeTypeActe($t->code) === null)
            ->pluck('id');

        if ($idsHorsPerimetre->isEmpty()) {
            return;
        }

        Bareme::whereIn('type_acte_id', $idsHorsPerimetre)
            ->whereIn('libelle', self::GRILLE_CONSTITUTION)
            ->where('actif', true)
            ->update([
                'actif'       => false,
                'description' => 'Désactivé le 2026-09-28 : tarif de constitution, hérité par erreur '
                    . "d'un filtre sur la catégorie « société ». Ni une modification ni une dissolution "
                    . 'ne constitue de société.',
            ]);
    }

    /**
     * Libellés de la grille de constitution — ceux que la boucle société crée.
     *
     * Liste explicite plutôt qu'une désactivation en masse : `ReglesGestionBaremeSeeder` pose
     * sur `SOC-MOD` des tarifs légitimes (droit de cession, enregistrement du PV, DNSV) qu'une
     * désactivation aveugle éteindrait aussitôt semés.
     */
    private const GRILLE_CONSTITUTION = [
        'Honoraires forfaitaires',
        'Enregistrement fiscal acte',
        "Droits d'enregistrement des Statuts et de la DNSV",
        'Frais APIP et RCCM',
        'Immatriculation RCCM',
        'Obtention NIF',
        'Insertion JAL (Journal Annonces Légales)',
        'Timbres & rôles',
        'Dépôt statuts au greffe',
    ];

    /**
     * Libellés que le seeder **ne produit plus**, à éteindre partout.
     *
     * `Immatriculation RCCM` (150 000) et `Obtention NIF` (75 000) ont été fusionnés dans
     * `Frais APIP et RCCM` (490 000) : l'APIP délivre les deux en une démarche. Mais un
     * `updateOrCreate` ne supprime jamais ce qu'il ne recrée pas — les deux anciennes lignes
     * sont donc **restées actives** sur les sept types de constitution, à côté de la nouvelle.
     *
     * Conséquence mesurée le 2026-09-29 : une constitution portait **deux** formalités APIP
     * déclarant toutes deux « Extrait RCCM définitif », et se voyait facturer 150 000 + 75 000
     * en trop. Le second défaut est visible sur la facture ; le premier ne l'était pas — il le
     * serait devenu au moment de saisir deux fois le même numéro au retour.
     *
     * ⚠️ **Liste explicite**, jamais « tout ce que le seeder ne crée plus » : l'étude peut
     * créer ses propres barèmes depuis Paramètres, et une désactivation par déduction les
     * éteindrait au premier `db:seed`.
     */
    private const LIBELLES_RETIRES = [
        'Immatriculation RCCM' => 'Fusionné dans « Frais APIP et RCCM » le 2026-09-29 : l\'APIP délivre le RCCM et le NIF en une seule démarche.',
        'Obtention NIF'        => 'Fusionné dans « Frais APIP et RCCM » le 2026-09-29 : l\'APIP délivre le RCCM et le NIF en une seule démarche.',
    ];

    /**
     * Éteint les libellés retirés, **partout et non seulement hors périmètre**.
     *
     * Désactivés et jamais supprimés, comme les trois autres corrections de ce seeder : une
     * facture émise les référence, et son montant doit rester explicable. Idempotent.
     */
    private function desactiverLibellesRetires(): void
    {
        foreach (self::LIBELLES_RETIRES as $libelle => $motif) {
            Bareme::where('libelle', $libelle)
                ->where('actif', true)
                ->update(['actif' => false, 'description' => $motif]);
        }
    }

    /**
     * Insère les barèmes pour un type d'acte donné, en évitant les doublons.
     * Retourne les Bareme indexés par libellé, pour permettre à l'appelant de
     * référencer leur id (ex. depend_de_bareme_id d'une démarche suivante).
     *
     * @return array<string, Bareme>
     */
    private function creerBaremes(int $typeActeId, array $baremes): array
    {
        $resultat = [];

        foreach ($baremes as $bareme) {
            $resultat[$bareme['libelle']] = Bareme::firstOrCreate(
                [
                    'type_acte_id' => $typeActeId,
                    'organisme'    => $bareme['organisme'],
                    'libelle'      => $bareme['libelle'],
                ],
                array_merge($bareme, [
                    'type_acte_id' => $typeActeId,
                    'actif'        => $bareme['actif'] ?? true,
                ])
            );
        }

        return $resultat;
    }
}
