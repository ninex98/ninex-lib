<?php
namespace Ninex\Lib\Contracts;

use Throwable;

interface TransactionAware
{
    public function beforeTransaction(): void;
    public function afterTransactionCommitted($result): void;
    public function handleTransactionError(Throwable $e): void;
}
