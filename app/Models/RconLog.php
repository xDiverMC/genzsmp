<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RconLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'command',
        'response',
        'success',
        'ip_address',
        'executed_by',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];
}
