<?php

namespace Ninex\Lib\Core;

use Exception;
use Throwable;

class ServiceException extends Exception
{
    private int $httpStatus;

    /** @var mixed Kept untyped for existing ServiceException subclasses. */
    protected $data;

    public function __construct(string $message = '', int $code = 400, $data = null, ?Throwable $previous = null, ?int $httpStatus = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
        $this->httpStatus = $httpStatus ?? ($code >= 400 && $code <= 599 ? $code : 400);
        if ($this->httpStatus < 400 || $this->httpStatus > 599) {
            throw new \InvalidArgumentException('An error HTTP status must be between 400 and 599.');
        }
    }

    public function getData()
    {
        return $this->data;
    }
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
