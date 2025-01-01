<?php

declare(strict_types=1);

namespace LLegaz\Redis\Exception;

class ConnectionLostException extends \Predis\PredisException
{
    public function __construct(string $message = 'Connection to redis server is lost or not responding' . PHP_EOL, int $code = 499, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
