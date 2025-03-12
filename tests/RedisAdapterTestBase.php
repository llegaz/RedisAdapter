<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests;

use LLegaz\Redis\RedisAdapter as SUT;
use LLegaz\Redis\RedisClientInterface;

/**
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapterTestBase extends \PHPUnit\Framework\TestCase
{
    /** @var RedisAdapter */
    protected SUT $redisAdapter;

    protected array $defaults = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->defaults = array_merge(RedisClientInterface::DEFAULTS, ['client_id' => 1337]);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        unset($this->redisAdapter);
    }

    protected function assertDefaultContext()
    {
        $this->assertEquals($this->defaults, $this->redisAdapter->getContext());
    }

    protected function assertNotDefaultContext()
    {
        $this->assertNotEquals($this->defaults, $this->redisAdapter->getContext());
    }
}
