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
                'name'       => 'Ayelama BAH',
                'email'      => 'ayelama.bah@notaire-guinee.com',
                'password'   => Hash::make('password'),
                'roles'      => [RoleUtilisateur::Notaire, RoleUtilisateur::Reviseur],
                'initiales'  => 'AB',
                'telephone'  => '+224 621 00 00 01',
                'actif'      => true,
            ],
            [
                'name'       => 'Nènè Aissata Kanté',
                'email'      => 'nene-aissata.kante@notaire-guinee.com',
                'password'   => Hash::make('password'),
                'roles'      => [RoleUtilisateur::Clerc],
                'initiales'  => 'NAK',
                'telephone'  => '+224 621 00 00 03',
                'actif'      => true,
            ],
            [
                'name'       => 'Mame Aissata Tafsir Camara',
                'email'      => 'mame-aissata.camara@notaire-guinee.com',
                'password'   => Hash::make('password'),
                'roles'      => [RoleUtilisateur::Clerc],
                'initiales'  => 'MATC',
                'telephone'  => '+224 621 00 00 05',
                'actif'      => true,
            ],
            [
                'name'       => 'Ibrahima Sory Fofana',
                'email'      => 'ibrahima-sory.fofana@notaire-guinee.com',
                'password'   => Hash::make('password'),
                'roles'      => [RoleUtilisateur::Formaliste],
                'initiales'  => 'ISF',
                'telephone'  => '+224 628 10 06 03',
                'actif'      => true,
            ],
            [
                'name'       => 'Fatoumata Diallo',
                'email'      => 'reviseur@ayelama.gn',
                'password'   => Hash::make('password'),
                'roles'      => [RoleUtilisateur::Reviseur],
                'initiales'  => 'FD',
                'telephone'  => '+224 621 00 00 02',
                'actif'      => true,
            ],
            [
                'name'       => 'Administrateur',
                'email'      => 'admin@ayelama.gn',
                'password'   => Hash::make('password'),
                'roles'      => [RoleUtilisateur::Administrateur],
                'initiales'  => 'AD',
                'telephone'  => '+224 621 00 00 00',
                'actif'      => true,
            ],
        ];

        foreach ($users as $data) {
            $roles = $data['roles'];
            unset($data['roles']);

            $user = User::firstOrCreate(['email' => $data['email']], $data);
            $user->syncRoles($roles);
        }
    }
}
