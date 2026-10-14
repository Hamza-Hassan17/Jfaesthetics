<?php

namespace Tests\Feature\Inventory;

use App\Support\RetryingTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\TestCase;

/**
 * No RefreshDatabase on purpose: retry only happens in the outermost
 * transaction, and RefreshDatabase would wrap the test in one. A scratch
 * table stands in for real work.
 */
class RetryingTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('retry_probe');
        Schema::create('retry_probe', fn ($table) => $table->string('code'));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('retry_probe');
        parent::tearDown();
    }

    private function deadlock(): QueryException
    {
        return new QueryException('update inventory_items ...', [], new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'));
    }

    public function test_a_deadlock_is_retried_and_only_the_successful_attempt_is_kept()
    {
        $attempts = 0;

        $result = RetryingTransaction::run(function () use (&$attempts) {
            $attempts++;
            DB::table('retry_probe')->insert(['code' => "attempt-$attempts"]);
            if ($attempts === 1) {
                throw $this->deadlock();
            }
            return 'saved';
        });

        $this->assertEquals('saved', $result);
        $this->assertEquals(2, $attempts);
        $this->assertEquals(['attempt-2'], DB::table('retry_probe')->pluck('code')->all(), 'The deadlocked attempt was rolled back.');
    }

    public function test_after_three_deadlocks_the_error_is_raised()
    {
        $attempts = 0;

        try {
            RetryingTransaction::run(function () use (&$attempts) {
                $attempts++;
                throw $this->deadlock();
            });
            $this->fail('Expected the deadlock to surface after the last attempt.');
        } catch (QueryException $e) {
            $this->assertEquals(RetryingTransaction::ATTEMPTS, $attempts);
        }
    }

    public function test_other_errors_are_not_retried()
    {
        $attempts = 0;

        try {
            RetryingTransaction::run(function () use (&$attempts) {
                $attempts++;
                throw new \DomainException('Over-receiving is blocked');
            });
        } catch (\DomainException $e) {
        }

        $this->assertEquals(1, $attempts);
    }
}
