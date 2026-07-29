<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionPoint extends Model
{
    protected $table = 'revision_points';

    protected $fillable = ['revision_id', 'point_id', 'etat', 'commentaire', 'perime'];

    protected function casts(): array
    {
        return [
            'perime' => 'boolean',
        ];
    }

    public function revision()
    {
        return $this->belongsTo(Revision::class);
    }
}
