<?php

declare(strict_types=1);

namespace LLegaz\Redis\Exception;

class LocalIntegrityException extends \Predis\PredisException
{
    public function __construct(string $message = 'Unexpected event occurs while syncing local (adapter) context with real redis client.' . PHP_EOL, int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
