<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderOverride extends Model
{
    protected $fillable = [
        'order_id', 'refund_status', 'refund_amount', 'refund_note', 'refund_reference',
        'refund_processed_by', 'refund_processed_at', 'flagged_for_stock_return',
        'cod_marked_paid', 'cod_marked_paid_by', 'cod_marked_paid_at',
    ];

    protected $casts = [
        'refund_processed_at' => 'datetime', 'cod_marked_paid_at' => 'datetime',
        'flagged_for_stock_return' => 'boolean', 'cod_marked_paid' => 'boolean', 'refund_amount' => 'integer',
    ];

    public function refundProcessedBy() { return $this->belongsTo(User::class, 'refund_processed_by'); }
    public function codMarkedPaidBy() { return $this->belongsTo(User::class, 'cod_marked_paid_by'); }
}
