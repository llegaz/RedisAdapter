<?php

declare(strict_types=1);

use Psr\Log\LogLevel;
use Symfony\Component\VarDumper\VarDumper;
use Throwable;

/**
 * DebugLogger uses VarDUmper::dump()
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class DebugLogger extends Psr\Log\AbstractLogger
{
    public function alert(string|\Stringable $message, mixed $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, mixed $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function debug(string|\Stringable $message, mixed $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function emergency(string|\Stringable $message, mixed $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function error(string|\Stringable $message, mixed $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function info(string|\Stringable $message, mixed $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function notice(string|\Stringable $message, mixed $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function warning(string|\Stringable $message, mixed $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

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
