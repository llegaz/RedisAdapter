<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests\Unit;

use LLegaz\Redis\Exception\ConnectionLostException;
use LLegaz\Redis\Exception\UnexpectedException;
use LLegaz\Redis\PredisClient;
use LLegaz\Redis\RedisAdapter as SUT;
use LLegaz\Redis\RedisClientInterface;
use Predis\Response\Status;

/**
 * Units using predis client
 *
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapterTest extends \LLegaz\Redis\Tests\RedisAdapterTestBase
{
    /** @var RedisClientInterface */
    protected $predisClient;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->predisClient = $this->getMockBuilder(PredisClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['disconnect', 'executeCommand'])
            ->addMethods(['ping', 'select' , 'client'])
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
        $this->redisAdapter = new SUT(
            RedisClientInterface::DEFAULTS['host'],
            RedisClientInterface::DEFAULTS['port'],
            null,
            RedisClientInterface::DEFAULTS['scheme'],
            RedisClientInterface::DEFAULTS['database'],
            $this->predisClient
        );
        $this->assertDefaultContext();
    }
    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testDummy()
    {
        $this->assertTrue(true);
    }

    public function testIsConnected()
    {
        $this->predisClient->expects($this->once())
                ->method('ping')
                ->willReturn(new Status('PONG'))
        ;
        $this->assertTrue($this->redisAdapter->isConnected());
        $this->assertDefaultContext();
    }

    public function testSelectDB()
    {
        $this->predisClient->expects($this->once())
                ->method('select')
                ->willReturn(new Status('OK'))
        ;
        $this->assertTrue($this->redisAdapter->selectDatabase(42));
        $this->assertNotDefaultContext();
    }

    public function testClientList()
    {
        $this->predisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willReturn([])
        ;
        $this->assertTrue(is_array($this->redisAdapter->clientList()));
        $this->assertDefaultContext();
    }

    /**
     * @expectedException LLegaz\Redis\Exception\ConnectionLostException
     */
    public function testSelectDBException()
    {
        $this->predisClient->expects($this->once())
            ->method('ping')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(ConnectionLostException::class);
        $this->redisAdapter->selectDatabase(42);
    }

    /**
     * @expectedException LLegaz\Redis\Exception\UnexpectedException
     */
    public function testSelectDBUnexpectedException()
    {
        $this->predisClient->expects($this->once())
            ->method('select')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(UnexpectedException::class);
        $this->redisAdapter->selectDatabase(42);
    }

    /**
     * @expectedException \LogicException
     */
    public function testSelectDBLogicException()
    {
        $this->expectException(\LogicException::class);
        $this->redisAdapter->selectDatabase(-42);
    }

    /**
     * @expectedException LLegaz\Redis\Exception\ConnectionLostException
     */
    public function testClientListException()
    {
        $this->predisClient->expects($this->once())
            ->method('ping')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(ConnectionLostException::class);
        $this->redisAdapter->clientList();
    }

    /**
     * @expectedException LLegaz\Redis\Exception\UnexpectedException
     */
    public function testClientListUnexpectedException()
    {
        $this->predisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(UnexpectedException::class);
        $this->redisAdapter->clientList();
    }

    public function testCheckIntegrity()
    {
        $a = [];
        $this->assertEquals(1, array_push($a, [/*'id' => 1337,*/ 'db' => 0, 'cmd' => 'client']));
        $this->predisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willReturn($a)
        ;
        $this->assertTrue($this->redisAdapter->checkIntegrity());
        $this->assertDefaultContext();
    }

    public function testCheckIntegritySwitchDB()
    {
        $a = [];
        $this->assertEquals(1, array_push($a, [/*'id' => 1337,*/ 'db' => 10, 'cmd' => 'client']));
        $this->predisClient->expects($this->once())
                ->method('select')
                ->willReturn(new Status('OK'))
        ;
        $this->assertTrue($this->redisAdapter->selectDatabase(10));
        $this->predisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willReturn($a)
        ;
        $this->assertTrue($this->redisAdapter->checkIntegrity());
        $this->assertNotDefaultContext();
    }

    /**
     * yes we could throw an exception from the later select too but it seems superfluous
     */
    public function testCheckIntegrityException()
    {
        $this->predisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willThrowException(new \Exception())
        ;
        $this->assertFalse($this->redisAdapter->checkIntegrity());
    }
}
