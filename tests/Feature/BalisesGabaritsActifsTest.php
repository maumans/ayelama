<?php

namespace Tests\Feature;

use Tests\TestCase;
use ZipArchive;

/**
 * Tout gabarit en service résout ses balises — sinon l'acte en porte le code.
 *
 * ⚠️ **Le générateur laisse intacte une balise qu'il ne sait pas remplir.** Ce n'est pas un
 * défaut : un gabarit hérité des statuts d'un confrère n'a aucune raison de porter nos
 * `${...}`, et le document produit est alors une copie fidèle. Mais sur **nos** gabarits, la
 * même tolérance fait sortir `${date_acte_lettres_sans_annee}` au milieu d'un acte
 * authentique, et personne ne le voit avant l'impression.
 *
 * Mesuré le 2026-11-02 : **dix balises** dans quatre gabarits actifs, dont les statuts de
 * SARLU — la forme de douze des quinze sociétés du registre. Trois causes distinctes :
 *
 *  1. un **nom inventé** pour une balise qui existait déjà : `date_acte_lettres_sans_annee`
 *     au lieu de `date_acte_lettres`, qui est précisément « jour et mois sans l'année ».
 *     Corrigé dans les trois gabarits concernés ;
 *  2. un **champ que le questionnaire ne collectait pas** : les contrats de bail fournis par
 *     l'étude citent le lieu et la date de naissance du locataire, absents du rôle. Ajoutés ;
 *  3. un **champ que la fiche ne porte pas**, et qui demande un arbitrage — ce qui reste
 *     ci-dessous.
 *
 * ⚠️ **Le défaut ne se voyait pas parce que rien ne regardait.** `balises-resolvables.txt`
 * existait, le générateur existait, mais aucun test ne confrontait les gabarits à la liste.
 * C'est ce test-là qui manquait, pas la liste.
 */
class BalisesGabaritsActifsTest extends TestCase
{
    /**
     * Les balises qu'aucun gabarit ne peut encore résoudre, et pourquoi.
     *
     * **Liste énumérée, jamais une tolérance générale** : elle ne peut que rétrécir, et toute
     * balise non listée fait échouer le test. C'est la forme que le dépôt donne à un manque
     * connu — un blocage énuméré plutôt qu'un contrôle désactivé.
     *
     * @var array<string, string>
     */
    private const MANQUES_CONNUS = [
        // Le formulaire RCCM du greffe sépare « Nom » et « Prénoms » en deux cases ; la fiche
        // client n'en porte qu'une, `prenom_nom`. Découper sur l'espace reviendrait à deviner
        // lequel des mots est le patronyme — faux une fois sur deux en Guinée, où l'ordre
        // varie. À trancher avec l'étude : soit deux colonnes sur la fiche, soit une seule
        // case sur le gabarit.
        'ger.nom'                    => 'RCCM SARLU — la fiche ne sépare pas nom et prénoms',
        'ger.prenom'                 => 'RCCM SARLU — la fiche ne sépare pas nom et prénoms',
        // Le greffe demande la date de début d'activité, que ni `societes` ni le questionnaire
        // de constitution ne portent. Elle n'est pas la date de constitution : une société
        // peut être immatriculée avant de commencer.
        'soc.date_debut_activite_jma' => 'RCCM SARLU — aucune colonne ni aucun champ ne porte cette date',
    ];

    /** Les blocs répétables : `${associes.nom}` est résolu par le bloc, pas par la liste. */
    private const BLOCS = [
        'associes', 'gerants', 'actionnaires', 'administrateurs', 'membres',
        'modif.cedants', 'modif.cessionnaires', 'modif.souscripteurs', 'modif.repartition_apres',
    ];

    public function test_aucun_gabarit_actif_ne_porte_de_balise_inconnue(): void
    {
        $gabarits = $this->gabaritsSurLeDisque();

        // ⚠️ **Ignoré, jamais vert à vide.** `storage/app/private` est exclu du dépôt : sur une
        // copie fraîche ou en intégration continue, il n'y a aucun gabarit à contrôler. Un
        // test qui passerait là-dessus ferait croire que les gabarits ont été vérifiés.
        if ($gabarits === []) {
            $this->markTestSkipped(
                'Aucun gabarit dans storage/app/private/modeles — rien à contrôler ici. '
                . "Ce contrôle n'a de sens que là où les modèles de l'étude sont déposés.",
            );
        }

        $resolvables = $this->balisesResolvables();
        $inconnues   = [];

        foreach ($gabarits as $nom => $chemin) {
            foreach ($this->balisesDe($chemin) as $balise) {
                if ($this->estResolvable($balise, $resolvables)) {
                    continue;
                }

                if (array_key_exists($balise, self::MANQUES_CONNUS)) {
                    continue;
                }

                $inconnues[] = "{$nom} → \${{$balise}}";
            }
        }

        $this->assertSame(
            [],
            $inconnues,
            "Ces balises sortiront **telles quelles** dans l'acte produit : le générateur ne "
            . "touche pas à ce qu'il ne sait pas remplir. Ajoutez la donnée au questionnaire, "
            . "ou corrigez le gabarit — et si le manque est durable, inscrivez-le dans "
            . 'MANQUES_CONNUS avec sa raison.',
        );
    }

    public function test_la_liste_des_manques_connus_ne_contient_rien_de_perime(): void
    {
        // Un manque listé mais corrigé laisserait un trou permanent dans le contrôle : la
        // balise redeviendrait tolérée le jour où elle réapparaîtrait pour une autre raison.
        $resolvables = $this->balisesResolvables();
        $enTrop      = [];

        foreach (self::MANQUES_CONNUS as $balise => $raison) {
            if ($this->estResolvable($balise, $resolvables)) {
                $enTrop[] = $balise;
            }
        }

        $this->assertSame(
            [],
            $enTrop,
            'Ces balises sont désormais résolvables : retirez-les de MANQUES_CONNUS.',
        );
    }

    private function estResolvable(string $balise, array $resolvables): bool
    {
        if (in_array($balise, $resolvables, true)) {
            return true;
        }

        $racine = explode('.', $balise)[0];

        return in_array($balise, self::BLOCS, true)
            || in_array($racine, self::BLOCS, true)
            || in_array($balise, $this->balisesDeFacture(), true);
    }

    /**
     * Les balises que `FactureGeneratorService` pose lui-meme.
     *
     * ⚠️ **Une note de frais n'est pas un acte**, et ce n'est pas le meme generateur qui la
     * remplit : `balises-resolvables.txt` est le catalogue des actes, derive des
     * questionnaires, et ne connait donc ni `${fac.total}` ni `${ligne.designation}`. Les
     * exclure du controle aurait laisse les deux gabarits de facture sans aucune
     * verification ; on lit plutot ce que leur generateur declare.
     *
     * Lecture du source par expression reguliere, comme les autres controles de parite du
     * depot — c'est ce qui oblige les `setValue()` a rester ecrits en clair.
     *
     * @return array<int, string>
     */
    private function balisesDeFacture(): array
    {
        static $balises = null;

        if ($balises !== null) {
            return $balises;
        }

        $source = file_get_contents(app_path('Services/FactureGeneratorService.php'));

        // `setValue('fac.total', …)`, `setValue("ligne.montant#{$n}", …)` et
        // `cloneRow('ligne.numero', …)` : les trois formes que le service emploie.
        preg_match_all('/(?:setValue|cloneRow)\(\s*[\'"]([a-zA-Z0-9_.]+)/', $source, $trouvees);

        return $balises = array_values(array_unique($trouvees[1]));
    }

    /** @return array<int, string> */
    private function balisesResolvables(): array
    {
        $lignes = file(base_path('docs/kit-modeles/balises-resolvables.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return array_map('trim', $lignes ?: []);
    }

    /**
     * Les gabarits présents sur le disque — pas ceux que la base dit actifs.
     *
     * ⚠️ **Le disque, et non la base, et c'est délibéré.** Un `.docx` déposé dans
     * `storage/app/private/modeles` est un gabarit que l'étude peut activer d'un clic depuis
     * l'écran Modèles : le contrôler seulement lorsqu'il est actif reviendrait à découvrir le
     * défaut le jour de son premier usage. Et le test n'a alors besoin d'aucune base.
     *
     * @return array<string, string> nom du fichier → chemin absolu
     */
    private function gabaritsSurLeDisque(): array
    {
        $racine = storage_path('app/private/modeles');

        if (! is_dir($racine)) {
            return [];
        }

        $gabarits = [];
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && strtolower($fichier->getExtension()) === 'docx') {
                $gabarits[$fichier->getFilename()] = $fichier->getPathname();
            }
        }

        ksort($gabarits);

        return $gabarits;
    }

    /**
     * Les balises d'un .docx.
     *
     * ⚠️ **Les balises XML sont retirées avant la recherche.** Word découpe volontiers
     * `${soc.denomination}` entre plusieurs `<w:r>`, selon la correction orthographique et le
     * formatage : chercher dans le XML brut rend des comptes faux. Une première version de ce
     * contrôle annonçait « aucune balise » sur un gabarit qui en portait quinze.
     *
     * @return array<int, string>
     */
    private function balisesDe(string $chemin): array
    {
        $zip = new ZipArchive();

        if ($zip->open($chemin) !== true) {
            return [];
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        preg_match_all('/\$\{\/?([a-zA-Z0-9_.]+)\}/', strip_tags($xml), $trouvees);

        return array_values(array_unique($trouvees[1]));
    }
}
