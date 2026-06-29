<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionGrille extends Model
{
    protected $table = 'revision_grilles';

    protected $fillable = ['type_acte_id', 'version', 'est_active', 'points'];

    protected function casts(): array
    {
        return [
            'est_active' => 'boolean',
            'points'     => 'array',
        ];
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    public function scopeActive($query)
    {
        return $query->where('est_active', true);
    }

    public function groupes(): array
    {
        return collect($this->points)
            ->groupBy('groupe')
            ->toArray();
    }
}
