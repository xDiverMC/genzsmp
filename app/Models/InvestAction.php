<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_name',
        'action_type',
        'amount',
        'reason',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
    ];
}
