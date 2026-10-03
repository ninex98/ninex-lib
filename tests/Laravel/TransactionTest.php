<?php

namespace Ninex\Lib\Tests;

use Illuminate\Support\Facades\DB;
use Ninex\Lib\Tests\Fixtures\Item;

class TransactionTest extends TestCase
{
    private function runner(): object
    {
        return new class () {
            use \Ninex\Lib\Traits\Database\WithDbTransaction;
            public function run(\Closure $work, ?\Closure $after = null, ?\Closure $error = null)
            {
                if ($after) {
                    $this->afterTransaction($after);
                }
                if ($error) {
                    $this->onTransactionError($error);
                }
                return $this->transaction($work);
            }
            public function chunks(iterable $items, \Closure $work, int $size): void
            {
                $this->chunkedTransaction($items, $work, $size);
            }
        };
    }
    public function testAfterCommitWaitsForOuterCommitAndDoesNotRunAfterRollback(): void
    {
        $after = 0;
        $callback = function () use (&$after) {
            $after++;
        };
        DB::beginTransaction();
        $this->runner()->run(fn () => Item::create(['name' => 'committed']), $callback);
        $this->assertSame(0, $after);
        DB::commit();
        $this->assertSame(1, $after);
        DB::beginTransaction();
        $this->runner()->run(fn () => Item::create(['name' => 'rolled back']), $callback);
        DB::rollBack();
        $this->assertSame(1, $after);
        $this->assertSame(1, Item::count());
    }
    public function testPostCommitFailureIsReportedWithoutRetryingCommittedWork(): void
    {
        $reported = 0;
        $errors = 0;
        app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (\RuntimeException $e) use (&$reported) {
            $reported++;
            return false;
        });
        $result = $this->runner()->run(fn () => Item::create(['name' => 'ok']), fn () => throw new \RuntimeException('after'), function () use (&$errors) {
            $errors++;
        });
        $this->assertTrue($result->exists);
        $this->assertSame(1, $reported);
        $this->assertSame(0, $errors);
        $this->assertSame(1, Item::count());
    }
    public function testChunkingDoesNotConsumeGeneratorBeforeProcessing(): void
    {
        $processed = 0;
        $items = (function () use (&$processed) {
            yield 1;
            yield 2;
            $this->assertSame(2, $processed);
            yield 3;
        })();
        $this->runner()->chunks($items, function () use (&$processed) {
            $processed++;
        }, 2);
        $this->assertSame(3, $processed);
    }
}
