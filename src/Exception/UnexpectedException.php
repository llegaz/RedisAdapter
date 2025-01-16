<?php

declare(strict_types=1);

namespace LLegaz\Redis\Exception;

/**
 * those should really not happen: they are used to signified thorough investigation are needed !
 */
class UnexpectedException extends \Predis\PredisException
{
    public function __construct(string $message = 'Unexpected event occurs.' . PHP_EOL, int $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
