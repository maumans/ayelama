<?php

namespace App\Models;

use App\Enums\RoleUtilisateur;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $table = 'role_user';

    protected $fillable = ['user_id', 'role'];

    protected function casts(): array
    {
        return ['role' => RoleUtilisateur::class];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
