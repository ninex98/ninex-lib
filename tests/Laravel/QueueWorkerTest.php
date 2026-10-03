<?php

namespace Ninex\Lib\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Queue, Schema};
use Ninex\Lib\Tests\Fixtures\{CreateItemJob, Item};

class QueueWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database',
            'queue.connections.database' => ['driver' => 'database', 'connection' => 'testing', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90, 'after_commit' => false],
            'queue.failed' => ['driver' => 'database-uuids', 'database' => 'testing', 'table' => 'failed_jobs']]);
        Schema::create('jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
    }

    private function work(CreateItemJob $job): void
    {
        Queue::push($job);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function testWorkerDeserializesAndCommitsJob(): void
    {
        $this->work(new CreateItemJob());
        $this->assertSame(1, Item::count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function testWorkerDoesNotRetryAfterPostCommitFailure(): void
    {
        $this->work(new CreateItemJob(failAfter: true));
        $this->assertSame(1, Item::count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function testWorkerRollsBackAndRecordsFailedJob(): void
    {
        $this->work(new CreateItemJob(failDuring: true));
        $this->assertSame(0, Item::count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }
}
