<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            TypeActeSeeder::class,
            DossierSeeder::class,
            ModeleActeSeeder::class,
            ModeleCourrierSeeder::class,
            BaremeSeeder::class,
            // Après BaremeSeeder : les tarifs officiels du CR de juillet 2026 corrigent
            // certaines lignes de démonstration (le greffe passe de 100 000 à 180 000 GNF).
            ReglesGestionBaremeSeeder::class,
            // **En dernier des trois seeders de tarifs, et l'ordre est la règle** : celui-ci
            // confronte ce que les deux précédents ont posé aux notes de frais que l'étude a
            // fournies. Il corrige, désactive ce qu'une note contredit, et marque « à
            // confirmer » tout ce qu'aucune note ne donne. Le passer avant laisserait les deux
            // autres réécrire ses corrections à la ligne suivante.
            ReglesGestionHonorairesSeeder::class,
            // Les démarches de modification et de dissolution — tableau signé du
            // 2026-10-28. **Après** le seeder d'honoraires : il bascule `genere_formalite`
            // sur des lignes que celui-ci vient de confronter aux notes de frais, et pose
            // les siennes à zéro, marquées à confirmer.
            ReglesGestionFormalitesSeeder::class,
            // Catalogue des opérations sur les sociétés (Addendum v1.2 § 5, Tableaux 9 et 10).
            ReglesGestionOperationsSeeder::class,
        ]);
    }
}
