<?php

namespace App\Models;

use App\Enums\MoyenPaiement;
use Illuminate\Database\Eloquent\Model;

/**
 * Un versement de l'étude à un organisme pour un dossier — impôts, greffe, APIP, journal…
 *
 * C'est l'autre moitié des débours : la facture dit ce que le client doit **pour** l'organisme,
 * le décaissement dit ce que l'étude lui a **effectivement** versé. Leur écart est ce qu'il
 * reste à reverser ; l'écart entre l'encaissé et le versé est la provision que l'étude détient
 * pour le client — ou l'avance qu'elle lui fait (`App\Services\SituationFinanciere`).
 *
 * Jusqu'au 2026-11-23, le versement n'était qu'un nombre sur la formalité (`montant_paye`). Ce
 * nombre reste, comme **somme** des décaissements de la formalité, tenue ici : ses lecteurs (écran
 * Formalités, fiche dossier) n'ont pas à changer.
 */
class Decaissement extends Model
{
    protected $fillable = [
        'dossier_id', 'formalite_id', 'libelle', 'organisme', 'date_decaissement', 'montant',
        'moyen', 'reference', 'notes', 'via_depot', 'reprise', 'enregistre_par_id',
    ];

    protected function casts(): array
    {
        return [
            'date_decaissement' => 'date',
            'montant'           => 'decimal:2',
            'moyen'             => MoyenPaiement::class,
            'via_depot'         => 'boolean',
            'reprise'           => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // `formalites.montant_paye` = somme de ses décaissements, recalculée à chaque écriture.
        $resynchroniser = function (self $d) {
            foreach (array_unique(array_filter([$d->formalite_id, $d->getOriginal('formalite_id')])) as $id) {
                $somme = self::where('formalite_id', $id)->sum('montant');
                Formalite::whereKey($id)->update(['montant_paye' => $somme > 0 ? $somme : null]);
            }
        };

        static::saved($resynchroniser);
        static::deleted($resynchroniser);
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function formalite()
    {
        return $this->belongsTo(Formalite::class);
    }

    public function enregistrePar()
    {
        return $this->belongsTo(User::class, 'enregistre_par_id');
    }

    /** @return array<string, mixed> */
    public function versArray(): array
    {
        return [
            'id'            => $this->id,
            'formalite_id'  => $this->formalite_id,
            'libelle'       => $this->libelle,
            // « Direction des Impôts » et non le code « impots » : les versements repris portent le
            // code de la formalité ; une saisie libre reste telle qu'écrite.
            'organisme'     => \App\Enums\OrganismeFormalite::tryFrom((string) $this->organisme)?->label() ?? $this->organisme,
            'date'          => $this->date_decaissement?->format('d/m/Y'),
            'date_iso'      => $this->date_decaissement?->toDateString(),
            'montant'       => (float) $this->montant,
            'moyen'         => $this->moyen?->value,
            'moyenLabel'    => $this->moyen?->label(),
            'reference'     => $this->reference,
            'notes'         => $this->notes,
            'via_depot'     => $this->via_depot,
            'reprise'       => $this->reprise,
            'enregistrePar' => $this->enregistrePar?->name,
        ];
    }
}
