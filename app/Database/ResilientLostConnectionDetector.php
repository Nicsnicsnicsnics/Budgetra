<?php

namespace App\Database;

use Illuminate\Database\LostConnectionDetector;
use Illuminate\Support\Str;
use Throwable;

/**
 * Laravel ships a hardcoded list of "the connection died, reconnect and retry"
 * error messages. Postgres/Supavisor termination notices are not on it, so a
 * routine Supabase backend recycle came back to the traveler as a 500 page:
 *
 *   SQLSTATE[08006] [7] FATAL: terminating connection due to administrator command
 *   (Connection: pgsql, ... SQL: select * from "sessions" where "id" = ...)
 *
 * Nothing was wrong with the query — the pooler had simply called
 * pg_terminate_backend() on that backend. Because the message went
 * unrecognised, neither of Laravel's two retry layers engaged and the
 * exception surfaced verbatim.
 *
 * These messages are all transient: the next connect lands on a fresh backend.
 * Recognising them lets Connector::createConnection retry the connect and
 * Connection::tryAgainIfCausedByLostConnection reconnect and re-run the query.
 * The framework still refuses to retry anything mid-transaction
 * (Connection::handleQueryException bails when $transactions >= 1), so no
 * half-applied write can be silently replayed.
 */
class ResilientLostConnectionDetector extends LostConnectionDetector
{
    /**
     * Transient server-side disconnects Laravel's own list doesn't cover.
     *
     * @var list<string>
     */
    protected array $transientMessages = [
        // pg_terminate_backend() — pooler recycling, maintenance, restart.
        'terminating connection due to administrator command',
        'terminating connection due to idle-in-transaction session timeout',
        'terminating connection due to conflict with recovery',
        'terminating connection because protocol synchronization was lost',
        // Instance is mid-restart; a moment later it accepts connections again.
        'the database system is starting up',
        'the database system is shutting down',
        'the database system is in recovery mode',
        // Supavisor / PgBouncer dropping the client side of a pooled connection.
        'server conn crashed',
        'client_idle_timeout',
        'server_idle_timeout',
    ];

    public function causedByLostConnection(Throwable $e): bool
    {
        return parent::causedByLostConnection($e)
            || Str::contains($e->getMessage(), $this->transientMessages);
    }
}
