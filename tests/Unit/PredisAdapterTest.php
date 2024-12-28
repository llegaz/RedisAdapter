<?php

declare(strict_types=1);

namespace LLegaz\Predis\Tests\Unit;

use LLegaz\Predis\Exception\ConnectionLostException;
use Predis\Response\Status;

/**
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class PredisAdapterTest extends \LLegaz\Predis\Tests\PredisAdapterTestBase
{
    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
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
        $this->client->expects($this->once())
                ->method('ping')
                ->willReturn(new Status('PONG'))
        ;
        $this->assertTrue($this->predisAdapter->isConnected());
        $this->assertDefaultContext();
    }

    public function testSelectDB()
    {
        $this->client->expects($this->once())
                ->method('select')
                ->willReturn(new Status('OK'))
        ;
        $this->assertTrue($this->predisAdapter->selectDatabase(42));
        $this->assertNotDefaultContext();
    }

    public function testClientList()
    {
        $this->client->expects($this->once())
            ->method('client')
            ->with('list')
            ->willReturn([])
        ;
        $this->assertTrue(is_array($this->predisAdapter->clientList()));
        $this->assertDefaultContext();
    }

    /**
     * @expectedException LLegaz\Predis\Exception\ConnectionLostException
     */
    public function testSelectDBException()
    {
        $this->client->expects($this->once())
            ->method('select')
            ->willThrowException(new ConnectionLostException())
        ;
        $this->expectException(ConnectionLostException::class);
        $this->predisAdapter->selectDatabase(42);
    }

    /**
     * @expectedException \LogicException
     */
    public function testSelectDBLogicException()
    {
        $this->expectException(\LogicException::class);
        $this->predisAdapter->selectDatabase(-42);
    }

    /**
     * @expectedException LLegaz\Predis\Exception\ConnectionLostException
     */
    public function testClientListException()
    {
        $this->client->expects($this->once())
            ->method('client')
            ->with('list')
            ->willThrowException(new ConnectionLostException())
        ;
        $this->expectException(ConnectionLostException::class);
        $this->predisAdapter->clientList();
    }
}
