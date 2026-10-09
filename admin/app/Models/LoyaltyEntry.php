<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyEntry extends Model
{
    protected $fillable = [
        'member_id', 'member_name', 'type', 'points', 'order_id', 'reason', 'flagged_negative', 'created_by',
    ];

    protected $casts = ['points' => 'integer', 'flagged_negative' => 'boolean'];

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
