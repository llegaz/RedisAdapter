<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests;

/**
 * Static Class Property: used to spread variables across non isolated tests processes
 *
 *
 * @package RedisAdapter
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class TestState
{
    public static $adapterClassDisplayed = false;
}
