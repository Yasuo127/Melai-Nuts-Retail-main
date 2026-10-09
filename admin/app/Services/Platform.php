<?php

namespace App\Services;

use App\Services\Supabase\StaffIdentity;

/**
 * Small helper for the parts of the UI that differ between the shared Supabase data
 * (DATA_SOURCE=supabase, production) and the built-in demo data (DATA_SOURCE=mock).
 */
class Platform
{
    public function __construct(private StaffIdentity $identity) {}

    public function isSupabase(): bool
    {
        return config('melai.data_source') === 'supabase';
    }

    /**
     * Branches the signed-in user can see, each as ['id', 'name', 'lat', 'lng', 'daily_target'].
     * Supabase: the real branch list (owner: all, staff: their own) + map/target settings
     * from config/melai.php matched by name. Mock: config/melai.php as is.
     */
    public function branches(): array
    {
        if (! $this->isSupabase()) {
            return config('melai.branches');
        }

        $extras = collect(config('melai.branches'))->keyBy(fn ($b) => mb_strtolower($b['name']));

        return collect($this->identity->branches())->map(function ($b) use ($extras) {
            $x = $extras->get(mb_strtolower($b['name']), []);

            return ['id' => $b['id'], 'name' => $b['name'], 'lat' => $x['lat'] ?? null, 'lng' => $x['lng'] ?? null,
                'daily_target' => $x['daily_target'] ?? config('melai.default_daily_target')];
        })->values()->all();
    }

    /** Payment method keys as stored by the data source (the app stores cash as 'cash'). */
    public function paymentMethods(): array
    {
        return $this->isSupabase() ? ['gcash', 'maya', 'card', 'cash'] : ['gcash', 'maya', 'card', 'cod'];
    }

    /** The method an admin may mark paid by hand (cash collected on delivery / at the counter). */
    public function cashMethod(): string
    {
        return $this->isSupabase() ? 'cash' : 'cod';
    }

    public function branchName(?string $id): string
    {
        return collect($this->branches())->firstWhere('id', $id)['name'] ?? '—';
    }
}
