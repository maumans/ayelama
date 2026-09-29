<?php

namespace App\Support;

use App\Contracts\EffetSurLaFicheSociete;
use App\Models\Dossier;
use App\Models\User;
use App\Services\SocieteCycleVieService;
use App\Services\SocieteMutationService;

/**
 * Registre des effets qu'un dossier produit sur la fiche société, à son entrée en Expédition.
 *
 * **Seul endroit à modifier** pour brancher un nouvel effet. Chaque effet porte sa propre
 * condition d'application ({@see EffetSurLaFicheSociete::concerne()}), si bien que la condition
 * reste à côté du code qui l'exploite au lieu de migrer vers un aiguillage distant — c'est ce
 * qui distingue ce registre d'un `match` sur le code de type d'acte, lequel exigerait de plus
 * une branche par défaut.
 *
 * Le pluriel est délibéré : rien n'interdit qu'un dossier déclenche deux effets. Aucun ne le
 * fait aujourd'hui (une modification statutaire et une dissolution ne partagent pas de type
 * d'acte), mais les faire s'exclure aurait été une contrainte inventée.
 */
final class EffetsFicheSociete
{
    /** @var array<int, class-string<EffetSurLaFicheSociete>> */
    private const REGISTRE = [
        SocieteMutationService::class,
        SocieteCycleVieService::class,
    ];

    /**
     * Effets applicables à ce dossier, résolus par le conteneur.
     *
     * @return array<int, EffetSurLaFicheSociete>
     */
    public static function pour(Dossier $dossier): array
    {
        $effets = [];

        foreach (self::REGISTRE as $classe) {
            $effet = app($classe);

            if ($effet->concerne($dossier)) {
                $effets[] = $effet;
            }
        }

        return $effets;
    }

    /**
     * Applique tous les effets concernés. **Idempotent**, chaque effet devant l'être.
     *
     * @return array<string, array<string, array{avant: mixed, apres: mixed}>> nom d'effet ⇒ changements
     */
    public static function appliquer(Dossier $dossier, ?User $user = null): array
    {
        $journal = [];

        foreach (self::pour($dossier) as $effet) {
            $changements = $effet->appliquer($dossier, $user);

            if ($changements !== []) {
                $journal[$effet->nom()] = $changements;
            }
        }

        return $journal;
    }

    /**
     * Défait ce qui peut l'être — voir {@see EffetSurLaFicheSociete::annuler()} pour pourquoi
     * tous les effets ne se défont pas.
     *
     * @return array<string, array<string, array{avant: mixed, apres: mixed}>>
     */
    public static function annuler(Dossier $dossier, ?User $user = null): array
    {
        $journal = [];

        foreach (self::pour($dossier) as $effet) {
            $changements = $effet->annuler($dossier, $user);

            if ($changements !== []) {
                $journal[$effet->nom()] = $changements;
            }
        }

        return $journal;
    }
}
