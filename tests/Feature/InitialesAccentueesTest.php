<?php

namespace Tests\Feature;

use App\Models\Partie;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les initiales d'un nom accentué doivent rester de l'UTF-8 valide (2026-09-24).
 *
 * `User` et `Partie` dérivaient leurs initiales par `$mot[0]` — l'indexation d'une chaîne PHP,
 * qui rend un **octet**. La première lettre d'un prénom accentué (Éric, Élodie, Étienne, Ébrima)
 * en occupe deux : on obtenait donc un demi-caractère, et `json_encode()` renvoyait `false`.
 * Toute réponse portant ces initiales partait alors en 500 — la fiche dossier pour une partie,
 * la liste des brouillons pour un utilisateur — sans que le message nomme le nom en cause.
 *
 * Trouvé par un test qui échouait **au hasard**, selon les noms que Faker tirait en locale
 * `fr_FR`. Zéro cas en base à cette date : le défaut était latent, et l'aurait été jusqu'au jour
 * où l'étude aurait saisi un client prénommé Émile.
 *
 * Le contrôle porte sur l'encodage, pas sur la valeur : ce qu'on veut interdire, c'est qu'une
 * réponse devienne impossible à sérialiser.
 */
class InitialesAccentueesTest extends TestCase
{
    /** @return list<array{0: string, 1: string}> */
    public static function nomsAccentues(): array
    {
        return [
            'prénom accentué'            => ['Éric Dupont', 'ÉD'],
            'deux mots accentués'        => ['Élodie Ébrima', 'ÉÉ'],
            'accent en seconde position' => ['Amadou Étienne', 'AÉ'],
            'nom simple'                 => ['Mamadou BAH', 'MB'],
            'un seul mot'                => ['Fatoumata', 'F'],
            'espaces multiples'          => ['  Ibrahima   DIALLO ', 'ID'],
        ];
    }

    #[DataProvider('nomsAccentues')]
    public function test_les_initiales_dun_utilisateur_restent_encodables(string $nom, string $attendu): void
    {
        $user = new User(['name' => $nom]);

        $this->assertSame($attendu, $user->initiales);
        $this->assertTrue(
            mb_check_encoding($user->initiales, 'UTF-8'),
            "Les initiales de « {$nom} » ne sont pas de l'UTF-8 valide : toute réponse JSON les "
            . 'contenant échouerait en 500.',
        );
        $this->assertIsString(
            json_encode(['initiales' => $user->initiales], JSON_THROW_ON_ERROR),
        );
    }

    #[DataProvider('nomsAccentues')]
    public function test_les_initiales_dune_partie_restent_encodables(string $nom, string $attendu): void
    {
        $partie = new Partie(['nom' => $nom]);

        $this->assertSame($attendu, $partie->initiales);
        $this->assertIsString(
            json_encode(['initiales' => $partie->initiales], JSON_THROW_ON_ERROR),
        );
    }

    public function test_une_colonne_initiales_renseignee_est_respectee(): void
    {
        // La colonne prime sur la dérivation — l'étude peut imposer d'autres initiales.
        $user = new User(['name' => 'Éric Dupont', 'initiales' => 'xyz']);

        $this->assertSame('XYZ', $user->initiales);
    }

    public function test_un_nom_vide_ne_leve_pas(): void
    {
        $this->assertSame('', (new User(['name' => null]))->initiales);
        $this->assertSame('', (new Partie(['nom' => null]))->initiales);
    }
}
