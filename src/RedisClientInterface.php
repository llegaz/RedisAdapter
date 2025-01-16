<?php

declare(strict_types=1);

namespace LLegaz\Redis;

/**
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
interface RedisClientInterface
{
    final public const DEFAULTS = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'scheme' => 'tcp',
        'database' => 0,
        'persistent' => false,
    ];

    final public const PHP_REDIS = 'php-redis';
    final public const PREDIS = 'predis';

    final public const TIMEOUT = 3; // 3s

    public function disconnect();
    public function isConnected(): bool;

    /**
     * return if connection used is persisted at the end of the script
     *
     * @return bool
     */
    public function isPersistent(): bool;

    /**
     * @return string
     */
    public function toString(): string;
}
