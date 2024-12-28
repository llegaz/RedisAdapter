<?php

declare(strict_types=1);

namespace LLegaz\Predis;

/**
 * This interface is used for later work, I will have to put it in its own repository
 *
 * @todo put this in its own repository
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
interface RedisInterface
{
    /**
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     *
     *
     * @param int $db
     * @return bool
     * @throws ConnectionLostException
     */
    public function selectDatabase(int $db): bool;

    /**
     * return all information for this redis client
     * (it is equivalent to CLIENT LIST redis command)
     *
     * @return array
     * @throws ConnectionLostException
     */
    public function clientList(): array;
}
