import { Input } from '@/components/ui/input';
import { frDateToISO, isoDateToFR } from '@/lib/dates';

// Input date natif (calendrier du navigateur) qui stocke/restitue une valeur
// au format français JJ/MM/AAAA — le format attendu par le reste de l'app
// (questionnaires de dossier, génération d'actes .docx).
export function DateField({ value, onValueChange, ...props }) {
    return (
        <Input
            type="date"
            value={frDateToISO(value)}
            onChange={(e) => onValueChange(isoDateToFR(e.target.value))}
            {...props}
        />
    );
}
