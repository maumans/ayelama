<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalActivite extends Model
{
    protected $table = 'journal_activites';

    public $timestamps = false;

    protected $fillable = ['dossier_id', 'user_id', 'action', 'type', 'meta'];

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function enregistrer(
        Dossier $dossier,
        string $action,
        string $type = 'note',
        array $meta = [],
        ?User $user = null
    ): self {
        return self::create([
            'dossier_id' => $dossier->id,
            'user_id'    => $user?->id ?? auth()->id(),
            'action'     => $action,
            'type'       => $type,
            'meta'       => $meta,
            'created_at' => now(),
        ]);
    }
}
