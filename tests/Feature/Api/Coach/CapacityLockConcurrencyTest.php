<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CapacityLockConcurrencyTest extends TestCase
{
    public function test_capacity_row_lock_serializes_two_real_database_connections(): void
    {
        if ('mysql' !== DB::connection()->getDriverName()) {
            $this->markTestSkipped('This contention check requires MySQL or MariaDB.');
        }

        $base = config('database.connections.mysql');
        Config::set('database.connections.capacity_lock_a', $base);
        Config::set('database.connections.capacity_lock_b', $base);
        $first = DB::connection('capacity_lock_a');
        $second = DB::connection('capacity_lock_b');

        $planId = DB::table('subscription_plans')->where('key', 'free')->value('id');
        $this->assertNotNull($planId);

        try {
            $first->beginTransaction();
            $first->table('subscription_plans')->where('id', $planId)->lockForUpdate()->first();

            $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $second->beginTransaction();
            $blocked = false;
            try {
                $second->table('subscription_plans')->where('id', $planId)->lockForUpdate()->first();
            } catch (QueryException $exception) {
                $blocked = true;
                $this->assertStringContainsString('Lock wait timeout', $exception->getMessage());
            }

            $this->assertTrue($blocked, 'The second capacity writer was not serialized by the row lock.');
        } finally {
            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }
            if ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
            DB::purge('capacity_lock_a');
            DB::purge('capacity_lock_b');
        }
    }
}
