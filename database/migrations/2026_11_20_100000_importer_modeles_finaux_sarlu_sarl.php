<?php

use App\Models\ModeleActe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Installe les gabarits SARLU et SARL balisés sur les **modèles finaux** de l'étude.
 *
 * L'étude a remis le 2026-11-20 (`docs/ACTES/`) deux dossiers réels complets — KOUMI
 * INVESTMENTS LTD (SARLU) et NEXIUM (SARL) — dans leur rédaction définitive. Les gabarits en
 * service avaient été balisés sur d'anciens formulaires à pointillés : la nouvelle rédaction en
 * diffère (« Société à Responsabilité Limitée **Unipersonnelle** » à l'article 1, objet social
 * en liste, parts numérotées, comparution « Monsieur … Né à … le quinze Février … »). Analyse
 * complète : `docs/kit-modeles/06-MODELES-FINAUX-2026-11.md`.
 *
 * Les quatorze fichiers sont **dans le dépôt** (`Documents reçus/Modèles finaux 2026-11/`),
 * puisque `storage/app/private` ne l'est pas : c'est ce qui permet de rejouer l'installation en
 * production. Ils ne contiennent plus aucune donnée des deux clients — contrôlé fichier par
 * fichier, en-têtes et pieds de page compris.
 *
 * Avant : SARLU 5 gabarits actifs sur 7, SARL 2 sur 7. Après : 7 sur 7 pour chacune.
 *
 * ⚠️ Le **nom** de chaque modèle n'est pas touché : la régénération d'un acte retrouve son
 * gabarit par le nom (`ActesGeneratorService::regenererDocument()`).
 *
 * Idempotente. Sans effet là où les fichiers sources sont absents (base de test).
 */
return new class extends Migration
{
    private const SOURCE = 'Documents reçus/Modèles finaux 2026-11';

    /** nom du modèle => [sous-dossier source, fichier] — la destination est `modeles/societe/{fichier}`. */
    private const MODELES = [
        'Statuts SARLU'                          => ['SARLU', 'statuts-sarlu.docx'],
        'Page de garde SARLU'                    => ['SARLU', 'page-garde-sarlu.docx'],
        'Attestation de dépôt du capital SARLU'  => ['SARLU', 'attestation-capital-sarlu.docx'],
        "Déclaration sur l'honneur SARLU"        => ['SARLU', 'declaration-honneur-sarlu.docx'],
        'DNSV SARLU'                             => ['SARLU', 'dnsv-sarlu.docx'],
        'Insertion au JORG SARLU'                => ['SARLU', 'insertion-sarlu.docx'],
        'RCCM SARLU'                             => ['SARLU', 'rccm-sarlu.docx'],
        'Statuts de SARL'                        => ['SARL', 'statuts-sarl.docx'],
        'Page de garde SARL'                     => ['SARL', 'page-garde-sarl.docx'],
        'Attestation de dépôt du capital SARL'   => ['SARL', 'attestation-capital-sarl.docx'],
        "Déclaration sur l'honneur SARL"         => ['SARL', 'declaration-honneur-sarl.docx'],
        'DNSV SARL'                              => ['SARL', 'dnsv-sarl.docx'],
        'Insertion au JORG SARL'                 => ['SARL', 'insertion-sarl.docx'],
        'RCCM SARL'                              => ['SARL', 'rccm-sarl.docx'],
    ];

    public function up(): void
    {
        foreach (self::MODELES as $nom => [$dossier, $fichier]) {
            $source = base_path(self::SOURCE . "/{$dossier}/{$fichier}");
            $modele = ModeleActe::where('nom', $nom)->first();

            // Pas de fichier, pas d'activation : un gabarit actif sans fichier produirait un
            // document vierge en se croyant configuré.
            if (! $modele || ! is_file($source)) {
                continue;
            }

            $destination = "modeles/societe/{$fichier}";
            Storage::disk('local')->put($destination, file_get_contents($source));

            $modele->update([
                'chemin_fichier' => $destination,
                'version'        => '2.0',
                'est_actif'      => true,
            ]);
        }
    }

    /**
     * Pas de retour arrière : les anciens fichiers SARLU restent sur le disque à leur ancien
     * chemin, mais rebrancher des gabarits que l'étude a remplacés ne rendrait service à
     * personne. Un retour se fait en remettant `chemin_fichier` à la main, en connaissance de
     * cause.
     */
    public function down(): void
    {
    }
};
