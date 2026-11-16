// Une heure de questionnaire, lue sous sa forme canonique `HH:MM` (retour R02, 2026-11-16).
//
// Miroir de `App\Support\Heure::normaliser()` — même expression, mêmes bornes. `ag.heure` était un
// texte libre : « 10H », « 10h00 », « 10H30 »… Le questionnaire est un instantané, ces valeurs ne
// sont pas réécrites ; elles sont **lues** ici pour que le champ `time` les affiche au lieu de
// paraître vide — un champ vide à l'écran, enregistré tel quel, aurait effacé l'heure en silence.

const FORME = /^\s*(\d{1,2})\s*(?:h|heures?|:)\s*(\d{2})?\s*(?:min)?\s*$/i;

/** « 10H » → « 10:00 », « 10 h 30 » → « 10:30 » ; null si ce n'est pas une heure. */
export function normaliserHeure(valeur) {
    if (typeof valeur !== 'string' && typeof valeur !== 'number') return null;
    const m = String(valeur).match(FORME);
    if (!m) return null;

    const heures = Number(m[1]);
    const minutes = m[2] ? Number(m[2]) : 0;
    if (heures > 23 || minutes > 59) return null;

    return `${String(heures).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}
