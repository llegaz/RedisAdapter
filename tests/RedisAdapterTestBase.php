<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests;

use LLegaz\Redis\RedisAdapter as SUT;
use LLegaz\Redis\RedisClientInterface;

/**
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
abstract class RedisAdapterTestBase extends \PHPUnit\Framework\TestCase
{
    /** @var RedisAdapter */
    protected SUT $redisAdapter;

    protected array $defaults = [];

    // if our class is not in paranoid mode the calls flow isn't the same
    protected int $OneLessCall = 0;

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

    /**
     * Client List call expectation for paranoid mode (integrity check are mandatory
     * because multiple access to redis clients pool are simulated through units)
     *
     */
    protected function integrityCheckCL()
    {
        // if our class is not in paranoid mode the calls flow isn't the same
        $this->getSelfClient()->expects($this->redisAdapter->amiParanoid() ? $this->once() : $this->never())
            ->method('client')
            ->with('list')
            ->willReturn([['id' => 1337, 'db' => 0, 'cmd' => 'client']])
        ;
    }

    abstract protected function getSelfClient(): RedisClientInterface;
}
