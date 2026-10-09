<?php

namespace App\Services\Data;

use App\Contracts\DataSource;
use Carbon\Carbon;
use Google\Cloud\Core\Timestamp;
use Kreait\Laravel\Firebase\Facades\Firebase;

/**
 * Real data from the mobile app's Firestore. Needs: composer require kreait/laravel-firebase
 * and FIREBASE_CREDENTIALS in .env. Only orders() and drivers() are wired so far; the rest
 * still need the real collection names, so they fail loudly instead of returning wrong numbers.
 */
class FirestoreDataSource implements DataSource
{
    private function db() { return Firebase::firestore()->database(); }

    private function date($v): ?Carbon { return $v instanceof Timestamp ? Carbon::instance($v->get()) : ($v ? Carbon::parse($v) : null); }

    public function orders(Carbon $from, Carbon $to): array
    {
        $docs = $this->db()->collection('orders')
            ->where('createdAt', '>=', new Timestamp($from->toDateTime()))
            ->where('createdAt', '<=', new Timestamp($to->toDateTime()))->documents();

        $out = [];
        foreach ($docs as $doc) {
            $d = $doc->data();
            $out[] = ['id' => $doc->id(), 'branch' => $d['branch'] ?? null, 'items' => $d['items'] ?? [], 'total' => (float) ($d['total'] ?? 0),
                'status' => $d['status'] ?? null, 'createdAt' => $this->date($d['createdAt'] ?? null),
                'paymentStatus' => $d['paymentStatus'] ?? null, 'paymentMethod' => $d['paymentMethod'] ?? null,
                'paidAt' => $this->date($d['paidAt'] ?? null), 'refundStatus' => $d['refundStatus'] ?? null,
                'refundAmount' => isset($d['refundAmount']) ? (float) $d['refundAmount'] : null,
                'customer' => $d['customerName'] ?? null, 'refundReason' => $d['refundReason'] ?? null, 'refundPhoto' => $d['refundPhotoUrl'] ?? null];
        }
        return $out;
    }

    public function drivers(): array
    {
        $out = [];
        foreach ($this->db()->collection('driverLocations')->documents() as $doc) { // CHANGE: collection name
            $d = $doc->data();
            $out[] = ['driverId' => $d['driverId'] ?? $doc->id(), 'name' => $d['name'] ?? 'Driver', 'status' => $d['status'] ?? 'On the way',
                'lat' => (float) $d['lat'], 'lng' => (float) $d['lng'], 'updatedAt' => optional($this->date($d['updatedAt'] ?? null))->toIso8601String()];
        }
        return $out;
    }

    public function stock(): array { throw new \LogicException('TODO: map Firestore stock collection'); }
    public function deliveries(): array { throw new \LogicException('TODO: map Firestore deliveries'); }
    public function loyalty(): array { throw new \LogicException('TODO: map loyaltyPoints / pointsHistory'); }
    public function loyaltyMembers(): array { throw new \LogicException('TODO: map customers collection + pointsHistory subcollection'); }
    public function pendingRefunds(): int { throw new \LogicException('TODO: count orders with refundStatus = requested'); }
}
