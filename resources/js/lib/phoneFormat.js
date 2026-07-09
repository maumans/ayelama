// Formatage des numéros de téléphone guinéens : groupes 3-2-2-2 (ex. "621 45 67 43").
// Le téléphone est un champ texte libre côté backend (aucune validation de format) :
// la valeur affichée EST la valeur stockée, contrairement aux champs numériques.

export function formatPhoneDisplay(raw) {
    const digits = String(raw ?? '').replace(/\D/g, '').slice(0, 9);
    return [digits.slice(0, 3), digits.slice(3, 5), digits.slice(5, 7), digits.slice(7, 9)]
        .filter(Boolean)
        .join(' ');
}
