<?php

return [
    // First admin, created by the seeder. CHANGE these in .env before deploying.
    'admin' => [
        'name'     => env('ADMIN_NAME', 'Melai Admin'),
        'email'    => env('ADMIN_EMAIL', 'admin@melainuts.test'),
        'password' => env('ADMIN_PASSWORD', 'ChangeMe#2026'),
        // Optional: Firebase UID of the owner's staff_members row in Supabase. When set,
        // `php artisan db:seed` links the seeded admin to it (same check as melai:link-staff).
        'staff_uid' => env('ADMIN_STAFF_UID'),
    ],
    // Login rate limit (per email + IP)
    'login' => ['max_attempts' => 5, 'decay_seconds' => 60],

    // Where business data comes from:
    //   'supabase'  = the mobile app's real Supabase database (orders, payments, refunds,
    //                 stock, products, deliveries, loyalty). Use this in production.
    //   'mock'      = built-in demo data (no database needed; used by the automated tests).
    //   'firestore' = legacy option from before the app moved its business data to Supabase.
    //                 The app's Firestore rules now deny those collections, so do not use it.
    'data_source' => env('DATA_SOURCE', 'mock'),

    'supabase' => [
        'connection' => 'supabase',             // config/database.php connection name
        'order_window_days' => (int) env('SUPABASE_ORDER_WINDOW_DAYS', 90), // Payments/Refunds lists
    ],

    // CHANGE: sales thresholds. net / target below low_below = Low, above high_above = High, else Average.
    'sales' => ['low_below' => 0.7, 'high_above' => 1.0],

    // CHANGE: stock thresholds (total stock / par level). Below low_below = Low, from plenty_from = Plenty.
    // With Supabase there is no par level, so the ratio is "share of items above their restock threshold".
    'stock' => ['low_below' => 0.4, 'plenty_from' => 0.8],

    // CHANGE: branches.
    // Mock mode uses this list as the branches themselves.
    // Supabase mode takes the branch list from the database and only borrows the map position
    // and daily sales target from here, matched by branch NAME (case-insensitive). A branch
    // that is not listed here gets 'default_daily_target' and no map pin.
    'branches' => [
        ['id' => 'branch-calamba', 'name' => 'Calamba Branch', 'lat' => 14.2117, 'lng' => 121.1653, 'daily_target' => 6000],
        ['id' => 'branch-los-banos', 'name' => 'Los Baños Branch', 'lat' => 14.1651, 'lng' => 121.2413, 'daily_target' => 5000],
        ['id' => 'branch-santa-cruz', 'name' => 'Santa Cruz Branch', 'lat' => 14.2780, 'lng' => 121.4172, 'daily_target' => 4500],
    ],
    'default_daily_target' => (int) env('DEFAULT_DAILY_TARGET', 5000),

    // PayMongo (test mode). Only used in mock mode: the mobile app takes online payments
    // through HitPay, so with DATA_SOURCE=supabase online refunds are sent back through the
    // HitPay dashboard and then marked completed in the Refunds page.
    'paymongo' => [
        'mode' => env('PAYMONGO_MODE', 'test'),          // test | live (always test until PayMongo asks you to go live)
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    ],

    // Sidebar pages (slug => title). With DATA_SOURCE=supabase, Sales, Products, Inventory and
    // Deliveries show live data; the others stay placeholders (see README "Known limits").
    'sections' => [
        'sales' => 'Sales', 'products' => 'Products', 'inventory' => 'Inventory', 'deliveries' => 'Deliveries',
        'driver-tracking' => 'Driver Tracking', 'reports' => 'Reports',
    ],
];
