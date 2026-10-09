<?php

namespace App\Services\Supabase;

use App\Exceptions\BackendActionException;
use App\Exceptions\StaffNotLinkedException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Who the admin portal acts as in Supabase: the signed-in Laravel user's linked staff
 * account (users.staff_uid = staff_members.firebase_uid). Owners are linked to an owner
 * row, branch staff to their staff row. Supabase decides what each may see and do.
 */
class StaffIdentity
{
    private array $contexts = [];

    public function __construct(private SupabaseGateway $gateway) {}

    public function uid(?User $user = null): string
    {
        $user ??= Auth::user();
        if (! $user || blank($user->staff_uid)) {
            throw new StaffNotLinkedException('Your admin account is not linked to a Melai Nuts staff or owner account yet.');
        }

        return $user->staff_uid;
    }

    /**
     * get_my_staff_context() for a UID: ['status' => 'active'|'not_provisioned'|'inactive'|'suspended'|'invalid_branch',
     * 'profile' => [...], 'authorized_branches' => [['id','name'], ...]]. Cached for the request.
     */
    public function contextFor(string $uid): array
    {
        return $this->contexts[$uid] ??= $this->gateway->call($uid, 'select public.get_my_staff_context()::text as r') ?? [];
    }

    /** Context of the signed-in user. Throws if not linked or no longer active in the app. */
    public function current(): array
    {
        $ctx = $this->contextFor($this->uid());
        if (($ctx['status'] ?? null) !== 'active') {
            throw new BackendActionException($this->statusMessage($ctx['status'] ?? 'not_provisioned'));
        }

        return $ctx;
    }

    public function isOwner(): bool
    {
        return ($this->current()['profile']['role'] ?? null) === 'owner';
    }

    /** [['id' => uuid, 'name' => ...], ...] branches the signed-in user may work with. */
    public function branches(): array
    {
        return $this->current()['authorized_branches'] ?? [];
    }

    public function statusMessage(string $status): string
    {
        return match ($status) {
            'inactive' => 'The linked staff account has been deactivated in the Melai Nuts app.',
            'suspended' => 'The linked staff account is suspended in the Melai Nuts app.',
            'invalid_branch' => 'The linked staff account has no valid branch assigned in the Melai Nuts app.',
            default => 'The linked staff account is not registered in the Melai Nuts app.',
        };
    }
}
