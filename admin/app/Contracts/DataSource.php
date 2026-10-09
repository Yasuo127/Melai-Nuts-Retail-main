<?php

namespace App\Contracts;

use Carbon\Carbon;

/**
 * Everything the dashboard reads from the mobile app's data.
 * Order shape: id, branch (branch id), items[], total, status, createdAt, paymentStatus, paymentMethod,
 *              paidAt, refundStatus, refundAmount, customer, refundReason, refundPhoto (Carbon dates). Driver shape: driverId, name, lat, lng, updatedAt.
 */
interface DataSource
{
    public function orders(Carbon $from, Carbon $to): array;
    /** [branchId => [['product'=>, 'qty'=>, 'par'=>, 'reorder'=>], ...]] */
    public function stock(): array;
    public function drivers(): array;
    /** [['driverId','driver','branch'(id),'items','status','eta'(Carbon)]] status: Preparing|On the way|Delivered */
    public function deliveries(): array;
    /** ['members'=>int, 'issued_month'=>int, 'redeemed_month'=>int] */
    public function loyalty(): array;
    /**
     * Loyalty members with their Firestore-side points history (before any local admin adjustments).
     * [['id','name','email','cardNumber','tier','pointsHistory'=>[['orderId','type','points','at'(Carbon)], ...]]]
     * type: earned|redeemed|refunded
     */
    public function loyaltyMembers(): array;
    public function pendingRefunds(): int;
}
