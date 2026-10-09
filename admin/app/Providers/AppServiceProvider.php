<?php

namespace App\Providers;

use App\Contracts\DataSource;
use App\Services\Data\FirestoreDataSource;
use App\Services\Data\MockDataSource;
use App\Services\Data\SupabaseDataSource;
use App\Services\OrderQueryService;
use App\Services\Platform;
use App\Services\Supabase\SupabaseRepository;
use App\Services\Supabase\StaffIdentity;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The only place that decides where dashboard data comes from.
        $this->app->scoped(DataSource::class, fn ($app) => match (config('melai.data_source')) {
            'supabase' => $app->make(SupabaseDataSource::class),
            'firestore' => new FirestoreDataSource,
            default => new MockDataSource,
        });

        // One instance per request so the staff context and query results are fetched once.
        $this->app->scoped(StaffIdentity::class);
        $this->app->scoped(SupabaseRepository::class);
        $this->app->scoped(Platform::class);
        $this->app->scoped(OrderQueryService::class);
    }

    public function boot(): void {}
}
