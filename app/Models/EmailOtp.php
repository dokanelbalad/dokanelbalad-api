<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    protected $fillable = ['email', 'code', 'expires_at', 'verified'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified' => 'boolean',
        ];
    }
}