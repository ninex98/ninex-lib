<?php

namespace Ninex\Lib\Tests\Fixtures;

use Ninex\Lib\Jobs\LibJob;
use RuntimeException;

class CreateItemJob extends LibJob
{
    public function __construct(public bool $failDuring = false, public bool $failAfter = false)
    {
        parent::__construct();
        $this->setTries(1);
    }

    protected function execute()
    {
        Item::create(['name' => 'queued']);
        if ($this->failDuring) {
            throw new RuntimeException('job body failed');
        }
    }

    protected function afterExecute($result): void
    {
        if ($this->failAfter) {
            throw new RuntimeException('post-commit callback failed');
        }
        parent::afterExecute($result);
    }
}
