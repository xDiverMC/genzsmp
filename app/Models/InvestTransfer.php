<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestTransfer extends Model
{
    protected $fillable = [
        'sender_name',
        'receiver_name',
        'asset',
        'amount',
        'fee',
        'received_amount'
    ];

    protected $casts = [
        'amount' => 'float',
        'fee' => 'float',
        'received_amount' => 'float'
    ];
}
