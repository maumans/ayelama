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
        'name', 'email', 'password',
        'initiales', 'telephone', 'avatar', 'actif',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
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

    public function roles()
    {
        return $this->hasMany(UserRole::class);
    }

    // Scopes
    public function scopeWithRole(\Illuminate\Database\Eloquent\Builder $query, RoleUtilisateur|string|array $roles): \Illuminate\Database\Eloquent\Builder
    {
        $values = collect(is_array($roles) ? $roles : [$roles])
            ->map(fn ($r) => $r instanceof RoleUtilisateur ? $r->value : $r)
            ->all();

        return $query->whereHas('roles', fn ($q) => $q->whereIn('role', $values));
    }

    // Helpers rôles
    public function roleValues(): array
    {
        return $this->roles->pluck('role')->map(fn ($r) => $r->value)->all();
    }

    public function roleLabels(): array
    {
        return $this->roles->pluck('role')->map(fn ($r) => $r->label())->all();
    }

    public function hasRole(RoleUtilisateur|string $role): bool
    {
        $value = $role instanceof RoleUtilisateur ? $role->value : $role;

        return in_array($value, $this->roleValues(), true);
    }

    public function hasAnyRole(array $roles): bool
    {
        $values = array_map(fn ($r) => $r instanceof RoleUtilisateur ? $r->value : $r, $roles);

        return (bool) array_intersect($values, $this->roleValues());
    }

    public function syncRoles(array $roles): void
    {
        $values = collect($roles)
            ->map(fn ($r) => $r instanceof RoleUtilisateur ? $r->value : $r)
            ->unique()
            ->values();

        $this->roles()->delete();
        $this->roles()->createMany($values->map(fn ($v) => ['role' => $v])->all());
        $this->unsetRelation('roles');
    }

    public function estAdministrateur(): bool
    {
        return $this->hasRole(RoleUtilisateur::Administrateur);
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

    // Pour l'API Inertia
    public function toInertiaArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'roles'      => $this->roleValues(),
            'roleLabels' => $this->roleLabels(),
            'initiales'  => $this->initiales,
            'avatar'     => $this->avatar,
        ];
    }
}
