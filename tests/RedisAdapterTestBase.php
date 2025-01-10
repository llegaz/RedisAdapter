<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests;

use LLegaz\Redis\PredisClient;
use LLegaz\Redis\RedisAdapter as SUT;
use LLegaz\Redis\RedisClient;
use LLegaz\Redis\RedisClientInterface;
use Predis\Response\Status;

/**
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapterTestBase extends \PHPUnit\Framework\TestCase
{
    /** @var RedisAdapter */
    protected $redisAdapter;

    /** @var RedisClientInterface */
    protected $predisClient;

    /** @var RedisClientInterface */
    protected $redisClient;

    private array $defaults = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->defaults = array_merge(RedisClientInterface::DEFAULTS, ['client_id' => '1337']);
        $this->predisClient = $this->getMockBuilder(PredisClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['disconnect', 'executeCommand'])
            ->addMethods(['ping', 'select' , 'client'])
            ->getMock()
        ;
        $this->redisClient = $this->getMockBuilder(RedisClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['disconnect', 'ping', 'select' , 'client', 'launchConnection'])
            ->getMock()
        ;
        $this->predisClient
            ->expects($this->any())
            ->method('disconnect')
            ->willReturnSelf()
        ;
        $this->predisClient
            ->expects($this->any())
            ->method('ping')
            ->willReturn(new Status('PONG'))
        ;
        $this->redisClient
            ->expects($this->any())
            ->method('disconnect')
            ->willReturnSelf()
        ;
        $this->redisClient
            ->expects($this->any())
            ->method('ping')
            ->willReturn(true)
        ;
        /**
         * start using predis client mock on SUT
         */
        $this->redisAdapter = new SUT(
            RedisClientInterface::DEFAULTS['host'],
            RedisClientInterface::DEFAULTS['port'],
            null,
            RedisClientInterface::DEFAULTS['scheme'],
            RedisClientInterface::DEFAULTS['database'],
            $this->predisClient
        );
        //$this->redisAdapter = (new SUT())->setPredis($this->client);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        unset($this->client);
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
