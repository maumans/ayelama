<?php
$zip = new ZipArchive;
$zip->open('storage/app/private/modeles/facture-notariale-v2.docx');
$xml = $zip->getFromName('word/document.xml');
preg_match_all('/\$\{([^}]+)\}/', $xml, $matches);
$vars = array_unique($matches[1]);
sort($vars);
foreach ($vars as $v) echo "  \${$v}\n";
