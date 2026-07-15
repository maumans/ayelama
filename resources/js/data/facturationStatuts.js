// Statut de paiement d'une facture — calculé (pas stocké en base), voir
// Facture::statutPaiement() (app/Models/Facture.php).

export const STATUT_META = {
    impaye:  { label: 'Impayé',  badge: 'bg-danger-bg text-danger-text border-red-200',   border: 'border-l-danger' },
    partiel: { label: 'Partiel', badge: 'bg-amber-50 text-amber-700 border-amber-200',    border: 'border-l-warning' },
    paye:    { label: 'Payé',    badge: 'bg-success-bg text-success-text border-green-200', border: 'border-l-success' },
};
