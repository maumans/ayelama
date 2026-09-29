<?php
/**
 * Vérifie qu'un modèle .docx ne porte que des balises que le moteur sait remplir.
 *
 *   php tools/verifier-balises.php "Documents reçus/SARL/STATUTS_balises.docx"
 *   php tools/verifier-balises.php "Documents reçus/SARL/"*.docx
 *
 * Le contrôle qui manquait : une balise inconnue n'échoue pas à la génération, elle
 * laisse un trou dans l'acte et une ligne dans l'historique du dossier que personne ne
 * lit. Trois modèles en service portaient ainsi des balises jamais remplies.
 *
 * La liste de référence est produite par `node tools/generer-balises-resolvables.mjs`.
 * Ce script REFUSE de tourner si elle est plus vieille que questionnaires.js : une
 * liste périmée validerait des balises désormais fausses.
 */

$racine    = dirname(__DIR__);
$liste = $racine . '/docs/kit-modeles/balises-resolvables.txt';

// Toutes les sources dont la liste dérive. `questionnaires.js` était la seule vérifiée : un
// cas ajouté au catalogue des données au retour, ou une balise ajoutée au générateur, laissait
// le garde-fou vert sur une liste périmée — et une liste périmée est pire qu'aucune liste.
$sources = [
    $racine . '/resources/js/data/questionnaires.js',
    $racine . '/tools/generer-balises-resolvables.mjs',
    $racine . '/app/Enums/DonneeAuRetour.php',
];

if (!is_file($liste)) {
    fwrite(STDERR, "Liste de référence absente.
Lancez : node tools/generer-balises-resolvables.mjs
");
    exit(2);
}

foreach ($sources as $source) {
    if (is_file($source) && filemtime($source) > filemtime($liste)) {
        $nom = basename($source);
        fwrite(STDERR, "{$nom} a changé depuis la dernière génération de la liste.
Relancez : node tools/generer-balises-resolvables.mjs
");
        exit(2);
    }
}

$connues = array_flip(array_filter(array_map('trim', file($liste))));
$fichiers = array_slice($argv, 1);

if (!$fichiers) {
    fwrite(STDERR, "Usage : php tools/verifier-balises.php <modele.docx> [autre.docx …]\n");
    exit(2);
}

$totalInconnues = 0;

foreach ($fichiers as $fichier) {
    if (!is_file($fichier)) {
        printf("%-46s  introuvable\n", basename($fichier));
        continue;
    }
    if (strtolower(pathinfo($fichier, PATHINFO_EXTENSION)) !== 'docx') {
        printf("%-46s  ignoré — seul le .docx peut servir de gabarit\n", basename($fichier));
        continue;
    }

    $zip = new ZipArchive();
    if ($zip->open($fichier) !== true) {
        printf("%-46s  illisible (fichier .docx corrompu ?)\n", basename($fichier));
        continue;
    }

    // En-têtes et pieds de page comptent aussi : l'office y figure sur la plupart des actes.
    $xml = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nom = $zip->getNameIndex($i);
        if (preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $nom)) {
            $xml .= $zip->getFromIndex($i);
        }
    }
    $zip->close();

    $texte = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    preg_match_all('/\$\{([^}]{1,120})\}/u', $texte, $m);

    $balises = array_values(array_unique($m[1]));
    // Les fermetures de bloc `${/associes}` sont valides dès lors que l'ouverture l'est.
    $inconnues = array_values(array_filter(
        $balises,
        fn ($b) => !str_starts_with($b, '/') && !isset($connues[$b]),
    ));

    $totalInconnues += count($inconnues);

    printf(
        "%-46s  %3d balise(s)  %s\n",
        basename($fichier),
        count($balises),
        $balises === []
            ? 'AUCUNE BALISE — document non normalisé, ou gabarit hérité (voir §7)'
            : ($inconnues === [] ? 'toutes résolvables' : count($inconnues) . ' NON RÉSOLVABLE(S)'),
    );

    foreach ($inconnues as $b) {
        echo "      ✗ \${{$b}}\n";
    }
}

exit($totalInconnues > 0 ? 1 : 0);
