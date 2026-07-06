<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facture extends Model
{
    protected $fillable = [
        'dossier_id',
        'note_numero',
        'note_date',
        'compte_numero',
        'objet',
        'assiette_chiffres',
        'total_chiffres',
    ];

    protected function casts(): array
    {
        return [
            'note_date'          => 'date',
            'assiette_chiffres'  => 'decimal:2',
            'total_chiffres'     => 'decimal:2',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function lignes()
    {
        return $this->hasMany(LigneFacture::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Recalcule le total à partir des lignes.
     */
    public function recalculerTotal(): self
    {
        $total = $this->lignes()->sum(\DB::raw('quantite * montant'));
        $this->update(['total_chiffres' => $total]);

        return $this;
    }

    /**
     * Génère un numéro de note automatique au format "N°XXX/MAB/AA"
     */
    public static function genererNumero(): string
    {
        $annee = date('y'); // "26"
        $last  = self::where('note_numero', 'like', "%/MAB/{$annee}")
            ->orderByDesc('id')
            ->value('note_numero');

        $seq = 1;
        if ($last && preg_match('/^(\d+)\//', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('%03d/MAB/%s', $seq, $annee);
    }
}
