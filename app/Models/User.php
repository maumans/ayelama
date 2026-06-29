<?php

namespace App\Models;

use App\Enums\RoleUtilisateur;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role',
        'initiales', 'telephone', 'avatar', 'actif',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => RoleUtilisateur::class,
            'actif'             => 'boolean',
        ];
    }

    // Relations
    public function dossiersRediges()
    {
        return $this->hasMany(Dossier::class, 'redacteur_id');
    }

    public function dossiersRevises()
    {
        return $this->hasMany(Dossier::class, 'reviseur_id');
    }

    public function dossiersNotaries()
    {
        return $this->hasMany(Dossier::class, 'notaire_id');
    }

    public function activites()
    {
        return $this->hasMany(JournalActivite::class);
    }

    // Helpers
    public function estAdministrateur(): bool
    {
        return $this->role === RoleUtilisateur::Administrateur;
    }

    public function getInitialesAttribute($value): string
    {
        if ($value) return strtoupper($value);
        return strtoupper(
            collect(explode(' ', $this->name))
                ->map(fn($w) => $w[0] ?? '')
                ->take(2)
                ->join('')
        );
    }

    public function getRoleLabel(): string
    {
        return $this->role?->label() ?? 'Utilisateur';
    }

    // Pour l'API Inertia
    public function toInertiaArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,
            'role'      => $this->role?->value,
            'roleLabel' => $this->getRoleLabel(),
            'initiales' => $this->initiales,
            'avatar'    => $this->avatar,
        ];
    }
}
