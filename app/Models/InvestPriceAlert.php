<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestPriceAlert extends Model
{
    protected $fillable = [
        'invest_user_id',
        'player_name',
        'asset',
        'target_price',
        'condition',
        'initial_price',
        'is_triggered',
        'triggered_at'
    ];

    protected $casts = [
        'target_price' => 'float',
        'initial_price' => 'float',
        'is_triggered' => 'boolean',
        'triggered_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(InvestUser::class, 'invest_user_id');
    }
}
