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
        ]);
    }
}
