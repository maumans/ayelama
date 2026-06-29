<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionPoint extends Model
{
    protected $table = 'revision_points';

    protected $fillable = ['revision_id', 'point_id', 'etat', 'commentaire'];

    public function revision()
    {
        return $this->belongsTo(Revision::class);
    }
}
