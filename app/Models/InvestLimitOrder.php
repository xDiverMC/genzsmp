<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestLimitOrder extends Model
{
    protected $fillable = [
        'invest_user_id',
        'player_name',
        'asset',
        'order_type',
        'amount',
        'target_price',
        'reserved_cost',
        'status',
        'filled_price',
        'filled_at'
    ];

    protected $casts = [
        'amount' => 'float',
        'target_price' => 'float',
        'reserved_cost' => 'float',
        'filled_price' => 'float',
        'filled_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(InvestUser::class, 'invest_user_id');
    }
}
