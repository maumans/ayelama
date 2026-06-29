<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'       => 'Maître Ibrahima Sow',
                'email'      => 'notaire@ayelema.gn',
                'password'   => Hash::make('password'),
                'role'       => RoleUtilisateur::Notaire,
                'initiales'  => 'IS',
                'telephone'  => '+224 621 00 00 01',
                'actif'      => true,
            ],
            [
                'name'       => 'Fatoumata Diallo',
                'email'      => 'reviseur@ayelema.gn',
                'password'   => Hash::make('password'),
                'role'       => RoleUtilisateur::Reviseur,
                'initiales'  => 'FD',
                'telephone'  => '+224 621 00 00 02',
                'actif'      => true,
            ],
            [
                'name'       => 'Mamadou Bah',
                'email'      => 'clerc@ayelema.gn',
                'password'   => Hash::make('password'),
                'role'       => RoleUtilisateur::Clerc,
                'initiales'  => 'MB',
                'telephone'  => '+224 621 00 00 03',
                'actif'      => true,
            ],
            [
                'name'       => 'Aïssatou Camara',
                'email'      => 'formaliste@ayelema.gn',
                'password'   => Hash::make('password'),
                'role'       => RoleUtilisateur::Formaliste,
                'initiales'  => 'AC',
                'telephone'  => '+224 621 00 00 04',
                'actif'      => true,
            ],
            [
                'name'       => 'Administrateur',
                'email'      => 'admin@ayelema.gn',
                'password'   => Hash::make('password'),
                'role'       => RoleUtilisateur::Administrateur,
                'initiales'  => 'AD',
                'telephone'  => '+224 621 00 00 00',
                'actif'      => true,
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(['email' => $data['email']], $data);
        }
    }
}
