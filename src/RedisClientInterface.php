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
     *
     * @param array $data a key/value pairs array to store in redis
     * @param int $ttl  Time To Live for all the associated data
     * @return bool
     */
    public function multipleSet(array $data, ?int $ttl = null): bool;

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

    /**
    * @param string $key
    * @param int $ttl
     * @return int|bool
     */
    public function expire($key, $ttl): int|bool;

    /**
    * @param string $key
    * @param string $field
     * @return string|null|false
     */
    public function hget($key, $field): string|null|false;

    /**
    * @param string $key
    * @param string $fields
     * @return array|false
     */
    public function hmget($key, $fields): array|false;

    /**
    * @param string $key
    * @param string $field
     * @return int|bool
     */
    public function hexists($key, $field): int|bool;

    /**
    * @param string $key
     * @return int
     */
    //public function incr($key): int;

    // next are SETs operations

    /**
     * Add one or more members
     * 
     * @param string $key
     * @param array<string|int, mixed> $members
     * @return int
     */
    //public function sadd(string $key, array $members): int;

    /**
     * Remove one or more members
     * 
     * @param string $key
     * @param array<string|int, mixed> $members
     * @return int
     */
    //public function srem(string $key, array $members): int;

    /**
     * Return all members
     * 
     * @param string $key
     * @return array the set members
     */
    //public function smembers(string $key): array;

    /**
     * Check if a member exists
     * 
     * @param string $key
     * @param mixed $member
     * @return bool
     */
    //public function sismember(string $key, mixed $member): bool;

    /**
     * Count members
     * 
     * @param string $key
     * @return int
     */
    //public function scard(string $key): int;
}
