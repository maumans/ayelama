<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTrustedDevice extends Model
{
    protected $fillable = [
        'user_id', 'token_hash', 'label', 'ip_address', 'last_used_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActif($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
