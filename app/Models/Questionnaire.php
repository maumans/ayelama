<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Questionnaire extends Model
{
    protected $fillable = ['dossier_id', 'donnees'];

    protected function casts(): array
    {
        return ['donnees' => 'array'];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->donnees, $key, $default);
    }
}
