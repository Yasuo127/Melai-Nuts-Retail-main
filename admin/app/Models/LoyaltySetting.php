<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    protected $fillable = ['earn_rate_pesos', 'redeem_points_per_peso', 'max_redeem_per_order', 'expiry_months'];

    /** There is only ever one settings row; create it with defaults the first time it's needed. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
