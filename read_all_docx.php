<?php
$file = 'Facture_MAB_SARLU_v2 (1).docx';
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (strpos($filename, '.xml') !== false && strpos($filename, 'word/') !== false) {
            echo "--- $filename ---\n";
            $content = $zip->getFromName($filename);
            $content = str_replace('<w:p ', "\n<w:p ", $content);
            $content = str_replace('</w:p>', "\n", $content);
            echo substr(strip_tags($content), 0, 1000) . "\n\n";
        }
    }
    $zip->close();
}
