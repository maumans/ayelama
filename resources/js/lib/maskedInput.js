// Helpers de repositionnement du curseur pour les champs à formatage en direct
// (NumberField, PhoneField) : on compte le nombre de chiffres avant le curseur dans
// la saisie brute, puis on replace le curseur après ce même nombre de chiffres dans
// le texte reformaté — indépendamment des espaces/séparateurs insérés ou supprimés.

export function countDigits(str) {
    return (str.match(/\d/g) || []).length;
}

export function positionAfterDigits(str, n) {
    if (n <= 0) return 0;
    let count = 0;
    for (let i = 0; i < str.length; i++) {
        if (/\d/.test(str[i])) {
            count++;
            if (count === n) return i + 1;
        }
    }
    return str.length;
}
