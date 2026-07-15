<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recu extends Model
{
    protected $fillable = ['paiement_id', 'numero', 'date_emission', 'chemin_fichier'];

    protected function casts(): array
    {
        return ['date_emission' => 'date'];
    }

    public function paiement()
    {
        return $this->belongsTo(Paiement::class);
    }

    /**
     * Numéro au format "{année}-{XXXX du dossier}-{séquence}", ex. "2026-0089-01",
     * dérivé de Dossier::reference (ex. "SOC-2026-0089" → préfixe "2026-0089").
     */
    public static function genererNumero(Dossier $dossier): string
    {
        $prefixe = preg_match('/^[A-Z]+-(\d{4}-\d+)$/', $dossier->reference, $m)
            ? $m[1]
            : $dossier->reference;

        $dernier = self::where('numero', 'like', "{$prefixe}-%")
            ->orderByDesc('id')
            ->value('numero');

        $seq = 1;
        if ($dernier && preg_match('/-(\d+)$/', $dernier, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('%s-%02d', $prefixe, $seq);
    }
}
