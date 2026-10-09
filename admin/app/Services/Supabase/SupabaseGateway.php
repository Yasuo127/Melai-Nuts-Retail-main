<?php

namespace App\Services\Supabase;

use App\Exceptions\BackendActionException;
use App\Exceptions\BackendUnavailableException;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;

/**
 * The only way this app talks to the mobile app's Supabase database.
 *
 * Every call runs inside one transaction that first does exactly what Supabase's own API
 * (PostgREST) does for a signed-in app user:
 *     set_config('request.jwt.claims', {"sub": <firebase uid>, "role": "authenticated"}, true)
 *     SET LOCAL ROLE authenticated
 * so Row Level Security and the staff/owner checks inside every SQL function apply to the
 * admin portal exactly as they apply to the Flutter app. The Laravel database login
 * (`melai_admin_portal`) has no privileges of its own.
 *
 * The UID always comes from StaffIdentity (the signed-in Laravel user's linked staff account),
 * never from a request parameter.
 */
class SupabaseGateway
{
    public function connection(): ConnectionInterface
    {
        return DB::connection(config('melai.supabase.connection', 'supabase'));
    }

    /**
     * Run $work as the given staff member. All statements share one transaction, so a
     * multi-step action either fully happens or not at all.
     *
     * @template T
     * @param  Closure(ConnectionInterface): T  $work
     * @return T
     */
    public function actingAs(string $firebaseUid, Closure $work): mixed
    {
        if ($firebaseUid === '' || strlen($firebaseUid) > 128) {
            throw new BackendActionException('Your admin account is not linked to a valid staff account.');
        }

        try {
            return $this->connection()->transaction(function (ConnectionInterface $db) use ($firebaseUid, $work) {
                $claims = json_encode(['sub' => $firebaseUid, 'role' => 'authenticated'], JSON_THROW_ON_ERROR);
                $db->select("select set_config('request.jwt.claims', ?, true)", [$claims]);
                $db->statement('set local role authenticated');

                return $work($db);
            });
        } catch (QueryException $e) {
            throw $this->translate($e);
        } catch (PDOException $e) {
            Log::error('[supabase] connection failed: '.$e->getMessage());
            throw new BackendUnavailableException('The Melai Nuts database could not be reached. Please try again in a moment.', 0, $e);
        }
    }

    /** Call one SQL function that returns json/jsonb (or nothing) and decode the result. */
    public function call(string $firebaseUid, string $sql, array $bindings = []): mixed
    {
        return $this->actingAs($firebaseUid, function (ConnectionInterface $db) use ($sql, $bindings) {
            $row = $db->selectOne($sql, $bindings);
            $value = $row ? (array) $row : [];
            $value = reset($value);

            // Functions that return void come back as '' when cast to text.
            if ($value === null || $value === false || $value === '') {
                return null;
            }

            return is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
        });
    }

    /** Turn a database error into something safe to show, keeping the details in the log. */
    private function translate(QueryException $e): \RuntimeException
    {
        $state = $e->errorInfo[0] ?? $e->getCode();
        $raw = (string) ($e->errorInfo[2] ?? $e->getMessage());

        // P0001 = RAISE EXCEPTION inside our SQL functions: written for humans, show it.
        // 28000 = not authenticated (no/blank UID).
        if (in_array($state, ['P0001', '28000'], true)) {
            $message = preg_match('/ERROR:\s+(.+?)(\n|$)/', $raw, $m) ? trim($m[1]) : 'The request was refused.';

            return new BackendActionException($message, 0, $e);
        }

        // 42501 = insufficient privilege: RLS / grants refused it.
        if ($state === '42501') {
            return new BackendActionException('You do not have permission to do that.', 0, $e);
        }

        Log::error('[supabase] query failed', ['sqlstate' => $state, 'error' => $raw,
            'hint' => $state === '42883' ? 'A function is missing: run supabase/migrations/20261009000000_admin_portal.sql.' : null]);

        // 08xxx = connection problems.
        if (is_string($state) && str_starts_with($state, '08')) {
            return new BackendUnavailableException('The Melai Nuts database could not be reached. Please try again in a moment.', 0, $e);
        }

        return new BackendUnavailableException('The Melai Nuts database returned an unexpected error. The details were logged for the administrator.', 0, $e);
    }
}
