<?php

declare(strict_types=1);

namespace LLegaz\Predis\Tests;

use LLegaz\Predis\PredisAdapter as SUT;
use Predis\Client;
use Predis\Response\Status;

/**
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class PredisAdapterTestBase extends \PHPUnit\Framework\TestCase
{
    /** @var PredisAdapter */
    protected $predisAdapter;

    /** @var Client */
    protected $client;

    /** @var array */
    protected const DEFAULTS = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'scheme' => 'tcp',
        'database' => 0,
        'persistent' => false,
    ];

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
        $this->predisAdapter = new SUT('127.0.0.1', 6379, null, 'tcp', 0, $this->client);
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
        $this->assertEquals(self::DEFAULTS, $this->predisAdapter->getContext());
    }

    protected function assertNotDefaultContext()
    {
        $this->assertNotEquals(self::DEFAULTS, $this->predisAdapter->getContext());
    }
}
