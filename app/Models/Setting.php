<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            static::set($key, $value);
        }
    }

    public static function all($columns = ['*'])
    {
        return parent::all($columns)->pluck('value', 'key');
    }

    /**
     * Utilisateurs pré-sélectionnés par défaut pour un nouveau dossier (Dossiers/Create
     * et conversion d'une Demande). Un défaut n'est retenu que si l'utilisateur existe
     * toujours, est actif et porte encore le rôle attendu — sinon on retombe sur "aucun"
     * plutôt que de pré-sélectionner un compte désactivé ou changé de rôle.
     */
    public static function defaultAssignees(): array
    {
        $roleParCle = [
            'notaire_id'    => ['default_notaire_id', 'notaire'],
            'reviseur_id'   => ['default_reviseur_id', 'reviseur'],
            'formaliste_id' => ['default_formaliste_id', 'formaliste'],
        ];

        $result = [];
        foreach ($roleParCle as $champ => [$settingKey, $role]) {
            $id = static::get($settingKey);
            $result[$champ] = $id && \App\Models\User::withRole($role)->where('actif', true)->whereKey($id)->exists()
                ? (int) $id
                : null;
        }

        return $result;
    }
}
