<?php

/**
 * ============================================================
 *  ROUTES API — POINT D'ENTRÉE PRINCIPAL
 *  Fichier : routes/api.php
 * ============================================================
 */

use Illuminate\Support\Facades\Route;

// Healthcheck
Route::get('ping', fn () => response()->json([
    'success' => true,
    'message' => 'Minizon API is alive 🚀',
    'version' => '1.0.0',
    'env'     => app()->environment(),
]))->name('api.ping');

// Diagnostic temporaire — À SUPPRIMER après correction
Route::get('debug', function () {
    $results = [];

    // 1. Config
    $results['cache_store']    = config('cache.default');
    $results['queue_conn']     = config('queue.default');
    $results['session_driver'] = config('session.driver');
    $results['app_env']        = app()->environment();

    // 2. Test cache (ce que ThrottleRequests utilise)
    try {
        \Cache::put('_debug_test', 'ok', 5);
        $val = \Cache::get('_debug_test');
        \Cache::forget('_debug_test');
        $results['cache_write_read'] = ($val === 'ok') ? 'OK' : 'FAIL (returned: ' . $val . ')';
    } catch (\Throwable $e) {
        $results['cache_write_read'] = 'ERROR: ' . $e->getMessage();
    }

    // 3. Test DB — roles
    try {
        $count = \App\Models\Role::count();
        $results['roles_count'] = $count;
    } catch (\Throwable $e) {
        $results['roles_count'] = 'ERROR: ' . $e->getMessage();
    }

    // 4. Test DB — admin profile
    try {
        $profile = \App\Models\Profile::where('email', 'admin@minizon.com')->first();
        $results['admin_profile'] = $profile ? 'FOUND (user_id=' . $profile->user_id . ')' : 'NOT FOUND';
    } catch (\Throwable $e) {
        $results['admin_profile'] = 'ERROR: ' . $e->getMessage();
    }

    // 5. Test personal_access_tokens table
    try {
        $count = \DB::table('personal_access_tokens')->count();
        $results['pat_table'] = 'OK (rows: ' . $count . ')';
    } catch (\Throwable $e) {
        $results['pat_table'] = 'ERROR: ' . $e->getMessage();
    }

    // 6. Test rate limiter directly
    try {
        $limiter = app(\Illuminate\Cache\RateLimiter::class);
        $key = '_debug_throttle_test';
        $limiter->hit($key, 60);
        $attempts = $limiter->attempts($key);
        $limiter->clear($key);
        $results['rate_limiter'] = 'OK (attempts=' . $attempts . ')';
    } catch (\Throwable $e) {
        $results['rate_limiter'] = 'ERROR: ' . $e->getMessage();
    }

    return response()->json(['success' => true, 'body' => $results]);
})->name('api.debug');

// -----------------------------------------------------------------------
// Modules de routes
// -----------------------------------------------------------------------

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/trips.php';
require __DIR__ . '/api/vehicles.php';
require __DIR__ . '/api/bookings.php';
require __DIR__ . '/api/payments.php';
require __DIR__ . '/api/withdrawals.php';
require __DIR__ . '/api/notifications.php';
require __DIR__ . '/api/chat.php';
require __DIR__ . '/api/disputes.php';
require __DIR__ . '/api/admin.php';

// Routes sandbox (jamais en production)
if (! app()->environment('production')) {
    require __DIR__ . '/api/sandbox.php';
}