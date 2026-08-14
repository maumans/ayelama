<?php
$source = 'Facture_MAB_SARLU_v2 (1).docx';
$output = 'storage/app/private/modeles/facture-notariale-v2.docx';

copy($source, $output);

$zip = new ZipArchive;
if ($zip->open($output) !== TRUE) {
    die("Impossible d'ouvrir\n");
}

$xml = $zip->getFromName('word/document.xml');

$doc = new DOMDocument;
$doc->loadXML($xml);
$xpath = new DOMXPath($doc);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

function paraText(DOMXPath $x, DOMElement $p): string {
    $texts = $x->query('.//w:t', $p);
    $s = '';
    foreach ($texts as $t) $s .= $t->textContent;
    return $s;
}

function replaceParaText(DOMXPath $x, DOMElement $p, string $newText, DOMDocument $doc): void {
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $runs = $x->query('./w:r', $p);
    if ($runs->length === 0) return;
    $firstRun = $runs->item(0);
    $rPr = $x->query('./w:rPr', $firstRun)->item(0);
    
    $toRemove = [];
    foreach ($p->childNodes as $child) {
        if ($child->nodeName === 'w:r' || $child->nodeName === 'w:proofErr') $toRemove[] = $child;
    }
    foreach ($toRemove as $node) $p->removeChild($node);
    
    $newRun = $doc->createElementNS($ns, 'w:r');
    if ($rPr) $newRun->appendChild($rPr->cloneNode(true));
    $tElem = $doc->createElementNS($ns, 'w:t');
    $tElem->setAttribute('xml:space', 'preserve');
    $tElem->textContent = $newText;
    $newRun->appendChild($tElem);
    $p->appendChild($newRun);
}

$paragraphs = $xpath->query('//w:p');
foreach ($paragraphs as $pIdx => $p) {
    $text = paraText($xpath, $p);
    
    if (preg_match('/N°\s+…/', $text)) {
        replaceParaText($xpath, $p, '${fac.numero}', $doc);
        continue;
    }
    if (preg_match('/Le\s+….*2026/', $text)) {
        replaceParaText($xpath, $p, '${fac.date}', $doc);
        continue;
    }
    if (preg_match('/^…{5,}$/', trim($text))) {
        replaceParaText($xpath, $p, '${fac.compte_numero}', $doc);
        continue;
    }
    if (strpos($text, 'Constitution de la Société') !== false || strpos($text, 'Société') !== false && strpos($text, '…') !== false) {
        replaceParaText($xpath, $p, '${fac.objet}', $doc);
        continue;
    }
    if (strpos($text, "Honoraires, droits d'enregistrement") !== false) {
        replaceParaText($xpath, $p, '${fac.detail}', $doc);
        continue;
    }
    if (trim($text) === 'Honoraires Forfaitaires') {
        replaceParaText($xpath, $p, '${ligne.designation}', $doc);
        continue;
    }
    if (trim($text) === '4 500 000') {
        replaceParaText($xpath, $p, '${ligne.montant}', $doc);
        continue;
    }
    if (trim($text) === '5 140 000') {
        replaceParaText($xpath, $p, '${fac.total}', $doc);
        continue;
    }
    if (strpos($text, 'Cinq Millions') !== false) {
        replaceParaText($xpath, $p, '${fac.total_lettres}', $doc);
        continue;
    }
}

$tables = $xpath->query('//w:tbl');
foreach ($tables as $tbl) {
    $tblText = '';
    foreach ($xpath->query('.//w:t', $tbl) as $t) {
        $tblText .= $t->textContent . ' ';
    }
    if (strpos($tblText, 'Désignation des prestations') === false) continue;
    
    $rows = $xpath->query('./w:tr', $tbl);
    $rowsToRemove = [];
    foreach ($rows as $rIdx => $row) {
        $rowText = trim(paraText($xpath, $row));
        
        // Supprimer lignes 2, 3, 4 (Droits, APIP, JAL)
        if ($rIdx >= 2 && $rIdx <= 4) {
            $rowsToRemove[] = $row;
        }
        
        // Ligne Timbres (5)
        if ($rIdx === 5) {
            $cells = $xpath->query('./w:tc', $row);
            $c0Paras = $xpath->query('./w:p', $cells->item(0));
            replaceParaText($xpath, $c0Paras->item(0), '${fac.num_timbres}', $doc);
        }
        
        // Ligne Rôles (6)
        if ($rIdx === 6) {
            $cells = $xpath->query('./w:tc', $row);
            $c0Paras = $xpath->query('./w:p', $cells->item(0));
            replaceParaText($xpath, $c0Paras->item(0), '${fac.num_roles}', $doc);
        }
    }
    
    foreach ($rowsToRemove as $row) {
        $row->parentNode->removeChild($row);
    }
    
    // Remplacer "1" par variables dans la ligne modèle (tr1)
    $rows2 = $xpath->query('./w:tr', $tbl);
    foreach ($rows2 as $row2) {
        if (strpos(paraText($xpath, $row2), '${ligne.designation}') === false) continue;
        
        $cells = $xpath->query('./w:tc', $row2);
        
        // N°
        $p0 = $xpath->query('./w:p', $cells->item(0))->item(0);
        if (trim(paraText($xpath, $p0)) === '1') {
            replaceParaText($xpath, $p0, '${ligne.numero}', $doc);
        }
        
        // Qté
        $p2 = $xpath->query('./w:p', $cells->item(2))->item(0);
        if (trim(paraText($xpath, $p2)) === '1') {
            replaceParaText($xpath, $p2, '${ligne.quantite}', $doc);
        }
    }
    break;
}

$zip->addFromString('word/document.xml', $doc->saveXML());
$zip->close();
echo "Template v2 généré avec succès.\n";
