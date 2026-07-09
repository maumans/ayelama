// Formatage "à la française" des champs numériques : espace = séparateur de milliers,
// virgule = séparateur décimal (ex. "10 000,6"). La valeur canonique manipulée par le
// reste de l'application (donnees JSON, validation Laravel, génération des actes via
// ActesGeneratorService qui teste is_numeric()) reste un nombre "propre" avec un POINT
// décimal et sans séparateur de milliers (ex. "10000.6") — seul l'affichage change.

export function toDisplay(raw, decimals = 0) {
    if (raw === null || raw === undefined || raw === '') return '';
    const [intPart, decPart] = String(raw).split('.');
    const grouped = (intPart || '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    if (decimals === 0 || decPart === undefined) return grouped;
    return `${grouped},${decPart.slice(0, decimals)}`;
}

// Reformate ce que l'utilisateur vient de taper et calcule en même temps la valeur canonique.
export function parseTyped(displayInput, decimals = 0) {
    const cleaned = displayInput.replace(/[^\d,.]/g, '');
    const sepIndex = decimals > 0 ? cleaned.search(/[,.]/) : -1;
    const intRaw = (sepIndex === -1 ? cleaned : cleaned.slice(0, sepIndex)).replace(/\D/g, '');
    const decRaw = sepIndex === -1 ? undefined : cleaned.slice(sepIndex + 1).replace(/\D/g, '').slice(0, decimals);
    const grouped = intRaw.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return {
        display: decRaw === undefined ? grouped : `${grouped},${decRaw}`,
        canonical: decRaw === undefined || decRaw === '' ? intRaw : `${intRaw}.${decRaw}`,
    };
}
