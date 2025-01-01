<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests;

use LLegaz\Redis\RedisAdapter as SUT;
use Predis\Client;
use LLegaz\Redis\RedisClientInterface;
use Predis\Response\Status;

/**
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapterTestBase extends \PHPUnit\Framework\TestCase
{
    /** @var PredisAdapter */
    protected $predisAdapter;

    /** @var Client */
    protected $client;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['disconnect', 'executeCommand'])
            ->addMethods(['ping', 'select' , 'client'])
            ->getMock()
        ;
        $this->client
            ->expects($this->any())
            ->method('disconnect')
            ->willReturnSelf()
        ;
        $this->client
            ->expects($this->any())
            ->method('ping')
            ->willReturn(new Status('PONG'))
        ;
        //$this->predisAdapter = (new SUT())->setPredis($this->client);
        $this->predisAdapter = new SUT(
            RedisClientInterface::DEFAULTS['host'],
            RedisClientInterface::DEFAULTS['port'],
            null,
            RedisClientInterface::DEFAULTS['scheme'],
            RedisClientInterface::DEFAULTS['database'],
            $this->client
        );
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        unset($this->client);
        unset($this->predisAdapter);
    }

    protected function assertDefaultContext()
    {
        $this->assertEquals(RedisClientInterface::DEFAULTS, $this->predisAdapter->getContext());
    }

    protected function assertNotDefaultContext()
    {
        $this->assertNotEquals(RedisClientInterface::DEFAULTS, $this->predisAdapter->getContext());
    }
}
