<?php

declare(strict_types=1);

namespace LLegaz\Redis\Logger;

use Psr\Log\AbstractLogger;

/**
 *
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class NullLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, mixed $context = []): void
    {
        // do nothing
    }
}
