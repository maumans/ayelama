<?php

namespace App\Enums;

enum StatutFormalite: string
{
    case ADeposer    = 'a_deposer';
    case Depose      = 'depose';
    case EnAttente   = 'en_attente';
    case RetourRecu  = 'retour_recu';
    case Rejete      = 'rejete';

    public function label(): string
    {
        return match($this) {
            self::ADeposer   => 'À déposer',
            self::Depose     => 'Déposé',
            self::EnAttente  => 'En attente de retour',
            self::RetourRecu => 'Retour reçu',
            self::Rejete     => 'Rejeté — à corriger',
        };
    }

    /**
     * La démarche est-elle achevée ?
     *
     * `RetourRecu` est l'état terminal depuis la suppression du statut `Cloture`
     * (l'étape de clôture par formalité a été retirée) : recevoir le retour de
     * l'organisme *est* l'aboutissement de la démarche.
     *
     * Volontairement un `match` exhaustif plutôt qu'une comparaison à une chaîne :
     * ajouter un cas à cet enum provoque une erreur PHP tant qu'il n'a pas été
     * classé ici. C'est précisément le mécanisme qui manquait — la comparaison
     * `!== 'cloture'` laissée dans DossierStepService après la suppression du
     * statut bloquait définitivement le passage à l'étape Expédition, sans qu'aucune
     * erreur ne le signale.
     */
    public function estTerminee(): bool
    {
        return match($this) {
            self::RetourRecu => true,
            self::ADeposer, self::Depose, self::EnAttente, self::Rejete => false,
        };
    }

    /**
     * La démarche exige-t-elle une correction avant de pouvoir aboutir ?
     *
     * Distingué de « simplement pas encore terminée » : un rejet demande une action
     * du formaliste, alors qu'une attente de retour ne dépend que de l'organisme.
     * Les deux bloquent l'avancement du dossier, mais pas pour la même raison — et
     * le message d'erreur doit le dire.
     */
    public function exigeCorrection(): bool
    {
        return $this === self::Rejete;
    }

    /**
     * Valeurs considérées comme terminales, pour les requêtes SQL qui ne peuvent
     * pas appeler estTerminee() sur chaque ligne.
     *
     * @return array<int, string>
     */
    public static function valeursTerminees(): array
    {
        // array_values : array_filter préserve les clés d'origine, ce qui produirait
        // un tableau non séquentiel — encodé en objet JSON plutôt qu'en tableau si
        // cette liste était un jour exposée au frontend.
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->estTerminee()),
        ));
    }
}
