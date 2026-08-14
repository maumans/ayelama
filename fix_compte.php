<?php
$zip = new ZipArchive;
$zip->open('storage/app/private/modeles/facture-notariale-v2.docx');
$xml = $zip->getFromName('word/document.xml');

// On remplace spécifiquement la séquence de points de suspension de la ligne compte
// P7: "……………………………"
// On le trouve juste après "N° Compte"
$xml = preg_replace('/(N° Compte.*?<\/w:p>.*?<w:t[^>]*>)…+/', '$1${fac.compte_numero}', $xml);

$zip->addFromString('word/document.xml', $xml);
$zip->close();
echo "Compte_numero injecté.\n";
