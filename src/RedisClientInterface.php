<?php

declare(strict_types=1);

namespace LLegaz\Redis;

/**
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
interface RedisClientInterface
{
    /**
     * return if connection used is persisted at the end of the script
     * 
     * @return bool
     */
    public function isPersistent(): bool;

    /**
     * @return string
     */
    public function __toString(): string;
}
