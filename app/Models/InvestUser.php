<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class InvestUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_name',
        'uuid',
        'is_bedrock',
        'pin_hash',
        'cash_balance',
        'last_login_at'
    ];

    protected $casts = [
        'is_bedrock' => 'boolean',
        'cash_balance' => 'float',
        'last_login_at' => 'datetime'
    ];

    public function portfolios()
    {
        return $this->hasMany(InvestPortfolio::class, 'invest_user_id');
    }

    public function trades()
    {
        return $this->hasMany(InvestTrade::class, 'player_name', 'player_name');
    }

    /**
     * Check if the user has set a trading PIN.
     */
    public function hasPin(): bool
    {
        return !empty($this->pin_hash);
    }

    /**
     * Verify 6-digit PIN.
     */
    public function verifyPin(string $pin): bool
    {
        if (empty($this->pin_hash)) {
            return false;
        }

        return Hash::check($pin, $this->pin_hash);
    }

    /**
     * Set a new 6-digit PIN.
     */
    public function setPin(string $pin): void
    {
        $this->pin_hash = Hash::make($pin);
        $this->save();
    }

    /**
     * Get or create an InvestUser by player name.
     */
    public static function findOrCreateByName(string $name): self
    {
        $cleanName = trim($name);
        $isBedrock = str_starts_with($cleanName, '.');

        $user = self::firstOrCreate(
            ['player_name' => $cleanName],
            [
                'is_bedrock' => $isBedrock,
                'cash_balance' => 10000.00, // Initial default balance
                'last_login_at' => now(),
            ]
        );

        // Ensure default asset portfolio entries exist
        $assets = ['btc', 'eth', 'gld', 'dia', 'emd'];
        foreach ($assets as $asset) {
            InvestPortfolio::firstOrCreate(
                [
                    'invest_user_id' => $user->id,
                    'asset' => strtoupper($asset)
                ],
                [
                    'player_name' => $cleanName,
                    'amount' => 0,
                    'avg_buy_price' => 0
                ]
            );
        }

        $user->update(['last_login_at' => now()]);

        return $user;
    }
}
