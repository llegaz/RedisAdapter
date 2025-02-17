<?php

declare(strict_types=1);

use Symfony\Component\VarDumper\VarDumper;
use Throwable;

/**
 * DebugLogger uses VarDUmper::dump()
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class DebugLogger extends Psr\Log\AbstractLogger
{
    public function log($level, string|\Stringable $message, mixed $context = []): void
    {
        try {
            if (is_string($level)) {
                $level .= ': ';
            } elseif ($level instanceof \Stringable) {
                $level = $level->__toString() . ': ';
            } else {
                $level = '';
            }

            VarDumper::dump($level . $message);
            if ($context && strlen(print_r($context, true))) {
                VarDumper::dump($context);
            }
        } catch (Throwable $t) {
            // do nothing
        }
    }
}
