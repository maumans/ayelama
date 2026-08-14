<?php
/**
 * Transforme Facture_MAB_SARLU_v2 (1).docx en template facture-notariale.docx
 * 
 * Stratégie : remplacer les textes statiques dans le document.xml par des
 * variables ${...} que PhpWord\TemplateProcessor sait remplir.
 */

$source = 'Facture_MAB_SARLU_v2 (1).docx';
$output = 'storage/app/private/modeles/facture-notariale-v2.docx';

// Copier le fichier source pour travailler dessus
copy($source, $output);

$zip = new ZipArchive;
if ($zip->open($output) !== TRUE) {
    die("Impossible d'ouvrir le fichier.\n");
}

// Lire le document.xml
$xml = $zip->getFromName('word/document.xml');

// Debug : afficher les runs XML pour trouver les textes exacts
// Les textes dans Word sont souvent fragmentés en plusieurs <w:r> (runs)
// On doit trouver les textes exacts pour les remplacer

// Extraire tous les textes <w:t> pour diagnostic
preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);
echo "=== Tous les textes du document ===\n";
foreach ($matches[1] as $i => $text) {
    if (trim($text) !== '') {
        echo "  [{$i}] \"" . trim($text) . "\"\n";
    }
}

$zip->close();
