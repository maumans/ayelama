<?php

namespace Database\Seeders;

use App\Models\Lieu;
use Illuminate\Database\Seeder;

/**
 * Amorce le référentiel des lieux.
 *
 * ⚠️ **Deux natures de données, délibérément distinguées.**
 *
 * Ce qui est **affirmé** : Conakry et ses 5 communes, les 33 préfectures. Ces découpages sont
 * stables et vérifiables.
 *
 * Ce qui est **marqué à vérifier** (`a_verifier = true`) : les quartiers. Leur liste n'est ni
 * garantie exhaustive ni garantie à jour — le découpage évolue, et certains quartiers changent de
 * commune de rattachement. Une donnée administrative fausse présentée comme sûre serait pire que du
 * texte libre dans une application notariale : l'étude les valide depuis
 * *Paramètres > Lieux*, filtre « à vérifier ».
 *
 * Idempotent : relançable sans créer de doublon, et **sans écraser** les corrections de l'étude —
 * un lieu déjà présent n'est jamais remis à `a_verifier`.
 */
class LieuSeeder extends Seeder
{
    /**
     * Communes de Conakry → quartiers connus.
     *
     * Conakry est traitée à part des préfectures : c'est une zone spéciale, et c'est là que se
     * trouve l'essentiel de la clientèle de l'étude (elle-même à Ratoma).
     */
    private const CONAKRY = [
        'Kaloum' => [
            'Almamya', 'Boulbinet', 'Coronthie', 'Kouléwondy', 'Manquepas', 'Sandervalia',
            'Sans-fil', 'Téminétaye', 'Tombo',
        ],
        'Dixinn' => [
            'Bellevue', 'Camayenne', 'Cameroun', 'Dixinn Centre', 'Dixinn Port', 'Hafia',
            'Kénien', 'Landreah', 'Minière', 'Belle-vue École',
        ],
        'Matam' => [
            'Bonfi', 'Boussoura', 'Coléah', 'Hermakonon', 'Madina', 'Matam Centre',
            'Mafanco', 'Touguiwondy',
        ],
        'Ratoma' => [
            'Bambéto', 'Cimenterie', 'Dar-es-Salam', 'Hamdallaye', 'Kaporo', 'Kipé', 'Kobaya',
            'Koloma', 'Lambanyi', 'Nongo', 'Ratoma Centre', 'Simbaya', 'Sonfonia', 'Taouyah',
            'Wanindara', 'Yattaya',
        ],
        'Matoto' => [
            'Béanzin', 'Dabompa', 'Dabondy', 'Gbessia', 'Kissosso', 'Matoto Centre', 'Sangoyah',
            'Simbaya Gare', 'Tanerie', 'Tombolia', 'Yimbaya',
        ],
    ];

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
        $conakry = $this->lieu(null, Lieu::NIVEAU_VILLE, 'Conakry');

        foreach (self::CONAKRY as $commune => $quartiers) {
            $lieuCommune = $this->lieu($conakry->id, Lieu::NIVEAU_COMMUNE, $commune);

            foreach ($quartiers as $quartier) {
                // Seuls les quartiers portent le drapeau : c'est là que mon information n'est pas
                // garantie.
                $this->lieu($lieuCommune->id, Lieu::NIVEAU_QUARTIER, $quartier, aVerifier: true);
            }
        }

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
