<?php

namespace Database\Seeders;

use App\Models\Lieu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Amorce le référentiel des lieux.
 *
 * ⚠️ **Deux natures de données, délibérément distinguées.**
 *
 * Ce qui est **affirmé** : les 33 préfectures et leur commune urbaine. Conakry, ses 13 communes et
 * leurs quartiers viennent de `database/data/quartiers-conakry-2024.json` via
 * `ayelema:lieux-conakry` : chaque quartier y porte la fiabilité de sa source — validé s'il vient du
 * MATD ou de l'INS, **à vérifier** s'il ne repose que sur Wikipédia ou si la liste elle-même doute.
 * Une donnée administrative fausse présentée comme sûre serait pire que du texte libre dans une
 * application notariale : l'étude valide le reste depuis *Paramètres > Lieux*, filtre « à vérifier ».
 *
 * Idempotent : relançable sans créer de doublon, et **sans écraser** les corrections de l'étude —
 * un lieu déjà présent n'est jamais remis à `a_verifier`.
 */
class LieuSeeder extends Seeder
{
    /**
     * Les 33 préfectures, groupées par région administrative.
     *
     * Chacune reçoit sa **commune urbaine** homonyme — c'est le découpage réel : une préfecture
     * compte une commune urbaine (le chef-lieu) et des communes rurales. Les communes rurales ne
     * sont pas amorcées : je ne peux pas en garantir la liste, et l'étude les ajoutera au besoin.
     */
    private const PREFECTURES = [
        'Boké'        => ['Boffa', 'Boké', 'Fria', 'Gaoual', 'Koundara'],
        'Faranah'     => ['Dabola', 'Dinguiraye', 'Faranah', 'Kissidougou'],
        'Kankan'      => ['Kankan', 'Kérouané', 'Kouroussa', 'Mandiana', 'Siguiri'],
        'Kindia'      => ['Coyah', 'Dubréka', 'Forécariah', 'Kindia', 'Télimélé'],
        'Labé'        => ['Koubia', 'Labé', 'Lélouma', 'Mali', 'Tougué'],
        'Mamou'       => ['Dalaba', 'Mamou', 'Pita'],
        'Nzérékoré'   => ['Beyla', 'Guéckédou', 'Lola', 'Macenta', 'Nzérékoré', 'Yomou'],
    ];

    public function run(): void
    {
        // Conakry, ses 13 communes et leurs quartiers : une seule source, le fichier de l'étude, et
        // une seule écriture, la commande qui le lit. Le seeder portait sa propre liste — celle
        // d'avant la loi L/2024/003, 5 communes — et une installation neuve repartait donc de
        // l'ancien découpage (2026-11-25).
        Artisan::call('ayelema:lieux-conakry', ['--appliquer' => true]);

        foreach (self::PREFECTURES as $prefectures) {
            foreach ($prefectures as $prefecture) {
                $ville = $this->lieu(null, Lieu::NIVEAU_VILLE, $prefecture);
                $this->lieu($ville->id, Lieu::NIVEAU_COMMUNE, $prefecture);
            }
        }
    }

    /**
     * Crée le lieu s'il manque, et **ne touche pas** à celui qui existe.
     *
     * `firstOrCreate` et non `updateOrCreate` : une relance du seeder ne doit pas remettre à
     * « à vérifier » un quartier que l'étude a validé, ni réactiver un lieu désactivé à dessein.
     */
    private function lieu(?int $parentId, string $niveau, string $nom, bool $aVerifier = false): Lieu
    {
        return Lieu::firstOrCreate(
            [
                'parent_id'     => $parentId,
                'niveau'        => $niveau,
                'nom_normalise' => \App\Support\Normalisation::comparable($nom),
            ],
            ['nom' => $nom, 'a_verifier' => $aVerifier],
        );
    }
}
