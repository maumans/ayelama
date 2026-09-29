<?php

namespace App\Services;

use App\Enums\FormeTitreRepresentation;
use App\Enums\MotifRepresentation;
use App\Models\Partie;

/**
 * Comment une partie comparaît à l'acte, rédigé.
 *
 * Produit la phrase que les modèles Word posent après l'identité d'une personne :
 *
 *   « A ce, présent »
 *   « ici représenté par Monsieur Mamadou BAH, …, en vertu d'une procuration sous seing privé
 *     en date du DOUZE MARS DEUX MILLE VINGT-SIX, laquelle demeurera annexée aux présentes
 *     après mention. »
 *
 * **Pourquoi une phrase composée côté serveur, et non deux variantes dans le modèle** :
 * `TemplateProcessor` n'a aucun conditionnel. Un modèle ne peut donc pas choisir entre
 * « présent » et « représenté ». La variabilité doit être absorbée dans la valeur, pas dans une
 * branche du gabarit — c'est la même raison qui a fait dériver `${pp.adresse}` et
 * `${bail.date_fin}` côté serveur.
 *
 * ⚠️ **Formules à faire relire par le notaire avant mise en service.** Elles suivent l'usage
 * notarial courant, mais l'étude a ses habitudes rédactionnelles — notamment sur l'accord de
 * « présent » et sur le visa d'une procuration consulaire.
 */
class MentionComparutionService
{
    /** Ce qu'on écrit quand la personne comparaît elle-même. */
    private const PRESENCE = 'A ce, présent';

    public function pour(Partie $partie): string
    {
        $motif = $partie->representation_motif;

        if (! $motif instanceof MotifRepresentation || $partie->representant === null) {
            return $this->presence($partie);
        }

        $intro = sprintf(
            'ici représenté%s par %s',
            $this->accord($partie),
            $this->identiteRepresentant($partie->representant),
        );

        // `match` exhaustif, sans `default` : ajouter un motif à l'enum doit faire échouer
        // PHP ici tant qu'on n'a pas décidé comment l'acte le dit. Une branche par défaut
        // produirait une comparution muette dans un acte authentique.
        return match ($motif) {
            MotifRepresentation::Procuration => $intro . $this->clauseProcuration($partie),
            MotifRepresentation::Legale      => $intro . $this->clauseLegale($partie),
            MotifRepresentation::Organique   => $intro . $this->clauseOrganique($partie),
        };
    }

    /**
     * « A ce, présent » / « présente » selon la civilité.
     *
     * Une personne morale « est ici présente » plutôt que « présent » — mais elle comparaît
     * de toute façon par son représentant, donc ce cas est rare.
     */
    private function presence(Partie $partie): string
    {
        return self::PRESENCE . $this->accord($partie);
    }

    /** Marque du féminin, déduite de la civilité de la fiche client quand elle existe. */
    private function accord(Partie $partie): string
    {
        $civilite = $partie->client?->civilite;

        if ($partie->type_personne === 'morale' || $civilite === 'Société') {
            return 'e';
        }

        return in_array($civilite, ['Mme', 'Mlle'], true) ? 'e' : '';
    }

    /** Nom, domicile et pièce d'identité du représentant, tels que l'acte les énonce. */
    private function identiteRepresentant(Partie $representant): string
    {
        $client   = $representant->client;
        $civilite = $client?->civilite;
        $titre    = match ($civilite) {
            'Mme'   => 'Madame',
            'Mlle'  => 'Mademoiselle',
            'M.'    => 'Monsieur',
            default => null,
        };

        $morceaux = [trim(($titre ? $titre . ' ' : '') . $representant->nom)];

        if (filled($representant->adresse)) {
            $morceaux[] = 'demeurant à ' . $representant->adresse;
        }

        if (filled($representant->cni)) {
            $piece = filled($client?->piece_type) ? $client->piece_type : 'pièce d\'identité';
            $morceaux[] = "titulaire de la {$piece} n° {$representant->cni}";
        }

        return implode(', ', $morceaux);
    }

    private function clauseProcuration(Partie $partie): string
    {
        $forme = $partie->representation_titre_forme;
        $date  = $this->dateDuTitre($partie);
        $qui   = $partie->representation_titre_autorite;

        // Sans forme renseignée, on n'invente pas : on vise une procuration, sans dire laquelle.
        if (! $forme instanceof FormeTitreRepresentation) {
            return ', en vertu d\'une procuration' . $date . $this->annexe();
        }

        // `match` exhaustif : chaque forme a son visa propre, et une forme nouvelle doit
        // forcer une décision de rédaction plutôt que retomber sur une formule générique.
        $visa = match ($forme) {
            FormeTitreRepresentation::SousSeingPrive => 'en vertu d\'une procuration sous seing privé',
            FormeTitreRepresentation::Notariee       => 'en vertu d\'une procuration reçue par '
                . ($qui ? 'Maître ' . $qui : 'un notaire'),
            FormeTitreRepresentation::Legalisee      => 'en vertu d\'une procuration sous seing privé'
                . ($qui ? ', signature légalisée par ' . $qui . ',' : ' à signature légalisée'),
            FormeTitreRepresentation::Consulaire     => 'en vertu d\'une procuration établie par '
                . ($qui ?: 'le poste consulaire'),
        };

        // L'expédition d'un acte authentique s'annexe, l'original d'un sous seing privé aussi :
        // seul le mot change.
        $annexe = $forme === FormeTitreRepresentation::Notariee
            ? ', dont une expédition demeurera annexée aux présentes après mention.'
            : $this->annexe();

        return ', ' . $visa . $date . $annexe;
    }

    private function clauseLegale(Partie $partie): string
    {
        $qualite = $partie->representation_qualite ?: 'représentant légal';
        $clause  = ", agissant en sa qualité de {$qualite} de la personne ci-dessus nommée";

        // Pas de visa inventé : tant que l'étude n'a pas dit quel document guinéen établit une
        // tutelle, on ne cite une décision que si le clerc en a saisi une.
        $date = $this->dateDuTitre($partie);
        if ($date === '' && blank($partie->representation_titre_autorite)) {
            return $clause;
        }

        return $clause . ', suivant décision'
            . (filled($partie->representation_titre_autorite) ? ' de ' . $partie->representation_titre_autorite : '')
            . $date;
    }

    private function clauseOrganique(Partie $partie): string
    {
        $qualite = $partie->representation_qualite ?: 'représentant légal';
        $clause  = ", agissant en sa qualité de {$qualite}";
        $date    = $this->dateDuTitre($partie);

        if ($date === '' && blank($partie->representation_titre_autorite)) {
            // Le gérant tient ses pouvoirs des statuts : rien à viser de plus.
            return $clause . ', dûment habilité aux termes des statuts';
        }

        return $clause . ', dûment habilité aux termes'
            . (filled($partie->representation_titre_autorite) ? ' de ' . $partie->representation_titre_autorite : ' de la délibération')
            . $date;
    }

    /** « en date du DOUZE MARS DEUX MILLE VINGT-SIX », ou rien si la date manque. */
    private function dateDuTitre(Partie $partie): string
    {
        $date = $partie->representation_titre_date;

        return $date ? ' en date du ' . NombreEnLettres::dateComplete($date) : '';
    }

    private function annexe(): string
    {
        return ', laquelle demeurera annexée aux présentes après mention.';
    }
}
