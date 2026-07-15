<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOtpCode extends Model
{
    protected $fillable = [
        'user_id', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'last_sent_at', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'consumed_at'  => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
