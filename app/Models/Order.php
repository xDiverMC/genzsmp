<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'gamertag',
        'edition',
        'item_name',
        'item_type',
        'price',
        'payment_method',
        'status',
        'rcon_command',
        'rcon_response',
        'delivered_at',
        'ip_address',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
    ];
}
