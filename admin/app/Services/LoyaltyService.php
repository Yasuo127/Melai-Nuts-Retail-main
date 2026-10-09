<?php

namespace App\Services;

use App\Contracts\DataSource;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltySetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Members and their points history come from Firestore (via DataSource) and are read-only there.
 * Manual adjustments, admin-triggered earning (COD orders marked paid), and refund deductions are
 * local admin actions, stored in loyalty_entries (Supabase-style), and merged on top here so every
 * page sees one consistent "effective" balance — same pattern as OrderQueryService for orders.
 */
class LoyaltyService
{
    public function __construct(private DataSource $data) {}

    public function settings(): LoyaltySetting
    {
        return LoyaltySetting::current();
    }

    public function updateSettings(array $attrs): LoyaltySetting
    {
        $settings = $this->settings();
        $settings->update($attrs);
        ActivityLogger::log('loyalty.settings_updated', 'Updated loyalty settings', null, $attrs);

        return $settings->fresh();
    }

    /** All members with Firestore history + local entries merged into one effective balance and totals. */
    public function members(?string $search = null): array
    {
        $members = $this->data->loyaltyMembers();
        $localByMember = LoyaltyEntry::whereIn('member_id', array_column($members, 'id'))->get()->groupBy('member_id');

        $out = array_map(function ($m) use ($localByMember) {
            $history = $this->mergedHistory($m, $localByMember->get($m['id'], collect()));
            $m['earned'] = $this->sumByType($history, 'earned');
            $m['redeemed'] = abs($this->sumByType($history, 'redeemed'));
            $m['refunded'] = abs($this->sumByType($history, 'refunded'));
            $m['adjusted'] = $this->sumByType($history, 'adjusted');
            $m['balance'] = max(0, collect($history)->sum('points'));
            $m['pointsHistory'] = $history;

            return $m;
        }, $members);

        if ($search) {
            $needle = mb_strtolower($search);
            $out = array_values(array_filter($out, fn ($m) => str_contains(mb_strtolower($m['name']), $needle)
                || str_contains(mb_strtolower($m['email']), $needle)
                || str_contains(mb_strtolower($m['cardNumber']), $needle)));
        }

        return $out;
    }

    public function find(string $memberId): ?array
    {
        return collect($this->members())->firstWhere('id', $memberId);
    }

    /** Merge Firestore history with the local ledger into one list, oldest first. */
    private function mergedHistory(array $member, $localEntries): array
    {
        $fromFirestore = collect($member['pointsHistory'] ?? [])->map(fn ($h) => [
            'type' => $h['type'], 'points' => $h['points'], 'order_id' => $h['orderId'] ?? null,
            'reason' => null, 'at' => $h['at'], 'source' => 'app',
        ]);
        $fromLocal = $localEntries->map(fn (LoyaltyEntry $e) => [
            'type' => $e->type, 'points' => $e->points, 'order_id' => $e->order_id,
            'reason' => $e->reason, 'at' => $e->created_at, 'source' => 'admin',
        ]);

        return $fromFirestore->merge($fromLocal)->sortBy('at')->values()->all();
    }

    private function sumByType(array $history, string $type): int
    {
        return (int) collect($history)->where('type', $type)->sum('points');
    }

    /**
     * Award points for a completed order exactly once (idempotent by order id + member).
     * Called when an admin marks a COD order paid; online orders are awarded by the mobile app itself.
     */
    public function awardForOrder(string $memberId, string $memberName, string $orderId, int $amountPesos): void
    {
        if (LoyaltyEntry::where(['member_id' => $memberId, 'order_id' => $orderId, 'type' => 'earned'])->exists()) {
            return; // already awarded for this order — never double-award
        }

        $points = intdiv($amountPesos, max(1, $this->settings()->earn_rate_pesos));
        if ($points <= 0) {
            return;
        }

        LoyaltyEntry::create([
            'member_id' => $memberId, 'member_name' => $memberName, 'type' => 'earned',
            'points' => $points, 'order_id' => $orderId,
        ]);
        ActivityLogger::log('loyalty.earned', "{$points} pts awarded to {$memberName} for order {$orderId}", null, [
            'member_id' => $memberId, 'order_id' => $orderId, 'points' => $points,
        ]);
    }

    /** Find a mock member by their order's customer name. Best-effort — real data links by customer id. */
    public function findMemberByName(string $name): ?array
    {
        return collect($this->data->loyaltyMembers())->first(fn ($m) => strcasecmp($m['name'], $name) === 0);
    }

    /** Deduct the points earned on a refunded order. Flags the member for review if it takes the balance below zero. */
    public function deductForRefund(string $memberId, string $memberName, string $orderId, int $pointsToDeduct): void
    {
        if ($pointsToDeduct <= 0) {
            return;
        }
        if (LoyaltyEntry::where(['member_id' => $memberId, 'order_id' => $orderId, 'type' => 'refunded'])->exists()) {
            return; // one deduction per refunded order
        }

        $balanceBefore = collect($this->find($memberId)['pointsHistory'] ?? [])->sum('points');
        $flag = ($balanceBefore - $pointsToDeduct) < 0;

        LoyaltyEntry::create([
            'member_id' => $memberId, 'member_name' => $memberName, 'type' => 'refunded',
            'points' => -$pointsToDeduct, 'order_id' => $orderId, 'flagged_negative' => $flag,
        ]);
        ActivityLogger::log('loyalty.refund_deducted', "{$pointsToDeduct} pts deducted from {$memberName} for refunded order {$orderId}", null, [
            'member_id' => $memberId, 'order_id' => $orderId, 'points' => $pointsToDeduct, 'flagged' => $flag,
        ]);
    }

    /** Manual admin add/deduct. Points must be a non-zero whole number and a reason is required; a deduction cannot exceed the current balance. */
    public function adjust(array $member, int $points, string $reason, User $admin): LoyaltyEntry
    {
        if ($points === 0) {
            throw ValidationException::withMessages(['points' => 'Enter a non-zero whole number of points.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for a manual adjustment.']);
        }
        if ($points < 0 && abs($points) > $member['balance']) {
            throw ValidationException::withMessages(['points' => 'You cannot deduct more points than the member\'s current balance ('.$member['balance'].').']);
        }

        $entry = LoyaltyEntry::create([
            'member_id' => $member['id'], 'member_name' => $member['name'], 'type' => 'adjusted',
            'points' => $points, 'reason' => $reason, 'created_by' => $admin->id,
        ]);

        ActivityLogger::log('loyalty.adjusted', ($points > 0 ? "+{$points}" : $points)." pts for {$member['name']}: {$reason}", $admin, [
            'member_id' => $member['id'], 'points' => $points, 'reason' => $reason,
        ]);

        return $entry;
    }
}
