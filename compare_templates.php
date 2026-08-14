<?php
// Script: extraire les balises ${...} du template actuel et du fichier de reference
function extractVariables(string $path): array {
    $zip = new ZipArchive;
    $vars = [];
    if ($zip->open($path) === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, '.xml') !== false) {
                $content = $zip->getFromName($name);
                preg_match_all('/\$\{([^}]+)\}/', $content, $matches);
                foreach ($matches[1] as $var) {
                    $vars[$var] = ($vars[$var] ?? 0) + 1;
                }
            }
        }
        $zip->close();
    }
    return $vars;
}

echo "=== Template actuel (facture-notariale.docx) ===\n";
$vars = extractVariables('storage/app/private/modeles/facture-notariale.docx');
ksort($vars);
foreach ($vars as $v => $count) {
    echo "  \${$v}  (x{$count})\n";
}

echo "\n=== Fichier de référence (Facture_MAB_SARLU_v2) ===\n";
$vars2 = extractVariables('Facture_MAB_SARLU_v2 (1).docx');
ksort($vars2);
if (empty($vars2)) {
    echo "  (aucune variable template — texte statique)\n";
} else {
    foreach ($vars2 as $v => $count) {
        echo "  \${$v}  (x{$count})\n";
    }
}
