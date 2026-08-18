<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestPortfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'invest_user_id',
        'player_name',
        'asset',
        'amount',
        'avg_buy_price'
    ];

    protected $casts = [
        'amount' => 'float',
        'avg_buy_price' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(InvestUser::class, 'invest_user_id');
    }
}
