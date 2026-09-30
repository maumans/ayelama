<?php

namespace Tests\Feature;

use Tests\TestCase;
use ZipArchive;

/**
 * Les balises `${soc.*}` d'un gabarit de dissolution sont fournies par le questionnaire de
 * dissolution — et pas seulement « connues quelque part » (2026-09-30).
 *
 * ⚠️ **Pourquoi `tools/verifier-balises.php` ne suffit pas.** Cet outil compare un gabarit à la
 * liste de **toutes** les balises résolvables, tous questionnaires confondus. Il répondait donc
 * « toutes résolvables » sur `dissolution.docx` alors que trois emplacements en sortaient vides :
 * `${soc.duree}`, `${soc.duree_lettres}` et `${soc.date_constitution}` (×2) sont bien déclarés
 * — par le questionnaire de **modification**, que ce gabarit n'utilise jamais. La question utile
 * n'est pas « cette balise existe-t-elle ? » mais « **ce** questionnaire la fournit-il ? ».
 *
 * La phrase touchée portait la décision :
 *
 *   « La durée de la société qui était initialement fixée à ${soc.duree_lettres} (${soc.duree})
 *     années, à dater du ${soc.date_constitution}, est réduite à ${dissolution.duree_reduite}. »
 *
 * Bornée aux deux gabarits de dissolution : la version générale, sur les 37 modèles, demanderait
 * de relier chaque fichier à son type d'acte via `ModeleActeSeeder`. Elle vaudrait le détour —
 * mais un test qui couvre deux gabarits et tourne vaut mieux qu'un test général qui reste à
 * écrire.
 */
class BalisesSocieteDissolutionTest extends TestCase
{
    private const GABARITS = [
        'storage/app/private/modeles/societe/dissolution.docx',
        'storage/app/private/modeles/societe/insertion-dissolution.docx',
    ];

    /**
     * Balises dérivées par le générateur, sans champ de questionnaire correspondant.
     *
     * `_lettres` et `_formate` sont calculées depuis leur montant ou leur nombre ;
     * `mention_liquidation` vient du statut de la fiche ({@see StatutSociete::mentionActe()}).
     * Les lister ici plutôt que de les deviner : une dérivation supposée à tort ferait passer
     * le test sur une balise réellement vide.
     */
    private const DERIVEES = [
        'soc.mention_liquidation',
        'soc.premier_exercice_annee',
        'soc.date_debut_activite_jma',
        'soc.gerant_actuel',
    ];

    /** Les clés `soc.*` que le questionnaire de dissolution déclare. */
    private function champsDuQuestionnaireDissolution(): array
    {
        // Fins de ligne normalisees : le depot travaille sous Windows, le fichier est en
        // CRLF, et un motif ancre sur un saut de ligne simple y bute silencieusement.
        $source = str_replace(
            "\r\n",
            "\n",
            file_get_contents(base_path('resources/js/data/questionnaires.js')),
        );

        $this->assertSame(
            1,
            preg_match('/\n    dissolution:\s*\[(.*?)\n    \],\n/s', $source, $bloc),
            'Le bloc `dissolution:` de questionnaires.js est introuvable — le regex a-t-il cessé de correspondre ?',
        );

        preg_match_all("/id:\s*'(soc\.[^']+)'/", $bloc[1], $captures);

        $champs = array_values(array_unique($captures[1]));
        $this->assertNotEmpty($champs, 'Aucun champ soc.* lu dans le questionnaire de dissolution.');

        return $champs;
    }

    /** @return array<int, string> les balises `${soc.*}` d'un .docx */
    private function balisesSociete(string $chemin): array
    {
        $absolu = base_path($chemin);
        $this->assertFileExists($absolu, "Gabarit attendu : {$chemin}");

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($absolu) === true, "Archive illisible : {$chemin}");

        $xml = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = $zip->getNameIndex($i);
            if (str_ends_with($nom, '.xml')) {
                $xml .= $zip->getFromIndex($i);
            }
        }
        $zip->close();

        // Word découpe volontiers une balise en plusieurs « runs » : on retire le balisage XML
        // avant de chercher, sinon `${soc.duree}` deviendrait invisible.
        $texte = preg_replace('/<[^>]+>/', '', $xml);

        preg_match_all('/\$\{(soc\.[^}]{1,60})\}/', $texte, $captures);

        return array_values(array_unique($captures[1]));
    }

    public function test_les_gabarits_de_dissolution_ne_citent_aucune_balise_societe_non_fournie(): void
    {
        $fournis = $this->champsDuQuestionnaireDissolution();
        $orphelines = [];

        foreach (self::GABARITS as $gabarit) {
            foreach ($this->balisesSociete($gabarit) as $balise) {
                if (in_array($balise, self::DERIVEES, true)) {
                    continue;
                }

                // `${soc.capital_lettres}` et `${soc.capital_formate}` se calculent depuis
                // `soc.capital_chiffres` : c'est ce champ-là qui doit être déclaré.
                $racine = preg_replace('/_(lettres|formate)$/', '', $balise);
                $source = in_array($racine . '_chiffres', $fournis, true)
                    ? $racine . '_chiffres'
                    : $racine;

                if (! in_array($source, $fournis, true)) {
                    $orphelines[] = sprintf('${%s} (%s)', $balise, basename($gabarit));
                }
            }
        }

        $this->assertSame(
            [],
            $orphelines,
            "Ces balises sortiront **vides** du gabarit : le questionnaire de dissolution ne les\n"
            . "déclare pas. Ajoutez le champ dans `resources/js/data/questionnaires.js`, section\n"
            . "« Société dissoute » — s'il est dans la table CHAMPS, il sera prérempli et masqué\n"
            . "automatiquement.\n  " . implode("\n  ", $orphelines),
        );
    }

    /** Régression nommée : la phrase qui réduit la durée de la société. */
    public function test_la_phrase_de_reduction_de_duree_a_ses_trois_champs(): void
    {
        $fournis = $this->champsDuQuestionnaireDissolution();

        foreach (['soc.duree', 'soc.date_constitution'] as $champ) {
            $this->assertContains(
                $champ,
                $fournis,
                "Sans {$champ}, la phrase « la durée … initialement fixée à … à dater du … est "
                . 'réduite à … » sort trouée du PV de dissolution.',
            );
        }
    }
}
