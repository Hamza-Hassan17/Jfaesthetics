<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * DB::transaction() with deadlock retry. Laravel re-runs the callback when
 * the outermost transaction fails with a deadlock or lock-wait timeout
 * (nested calls just rethrow up to the outermost one), so callbacks must
 * read their state from the database, not from values captured before a
 * failed attempt.
 *
 * Rule: database work only inside the callback. A retry rolls back the
 * database but cannot undo anything else, so SMS, email, notifications,
 * HTTP calls, file writes, cache writes, queued jobs/events, flash
 * messages, browser events and Livewire property changes must happen
 * after run() returns, never inside the callback (or inside anything it
 * calls). Activity-log rows are fine inside: they are database writes and
 * roll back with the failed attempt.
 *
 * Lock order every inventory write follows, acquired up front before any
 * other work, so purchasing and sales can't wait on each other in a cycle:
 *   1. suppliers
 *   2. purchase_orders
 *   3. goods_receipts
 *   4. purchase_order_items (ascending id)
 *   5. inventory_items      (ascending id)
 *   6. item_batches         (only while holding that batch's item)
 *   7. document_sequences   (last)
 */
class RetryingTransaction
{
    public const ATTEMPTS = 3;

    public static function run(callable $callback)
    {
        return DB::transaction($callback, self::ATTEMPTS);
    }
}
