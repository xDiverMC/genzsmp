<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestTrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_name',
        'trade_type',
        'asset',
        'amount',
        'price',
        'subtotal',
        'tax',
        'total'
    ];

    protected $casts = [
        'amount' => 'float',
        'price' => 'float',
        'subtotal' => 'float',
        'tax' => 'float',
        'total' => 'float',
    ];
}
