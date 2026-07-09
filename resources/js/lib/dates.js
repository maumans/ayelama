// Les questionnaires de dossier stockent les dates au format français JJ/MM/AAAA
// (ce format est injecté tel quel dans les actes .docx par ActesGeneratorService).
// Les <input type="date"> natifs exigent en revanche un value ISO AAAA-MM-JJ.
// Ces helpers font le pont entre les deux sans jamais changer le format stocké en base.

export function frDateToISO(value) {
    if (!value) return '';
    const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(value).trim());
    if (!m) return '';
    const [, d, mo, y] = m;
    return `${y}-${mo}-${d}`;
}

export function isoDateToFR(value) {
    if (!value) return '';
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value).trim());
    if (!m) return '';
    const [, y, mo, d] = m;
    return `${d}/${mo}/${y}`;
}
