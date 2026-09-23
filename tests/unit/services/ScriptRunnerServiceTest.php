<?php

declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2026 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use Elabftw\Exceptions\ImproperActionException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ScriptRunnerServiceTest extends TestCase
{
    public function testEmptyRunnerUrlThrows(): void
    {
        $service = new ScriptRunnerService(new Client());
        $this->expectException(ImproperActionException::class);
        $service->run('analyze.py');
    }

    public function testInvalidScriptNameThrows(): void
    {
        $mock = new MockHandler(array(new Response(200, array(), '{"output": "ok"}')));
        $service = new ScriptRunnerService($this->getClient($mock), 'http://127.0.0.1:8765');
        $this->expectException(ImproperActionException::class);
        $service->run('../evil');
    }

    public function testSuccessfulRun(): void
    {
        $mock = new MockHandler(array(new Response(200, array(), '{"output": "script output"}')));
        $service = new ScriptRunnerService($this->getClient($mock), 'http://127.0.0.1:8765');
        $this->assertSame('script output', $service->run('analyze.py', array('temperature' => 25)));
        $request = $mock->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('http://127.0.0.1:8765', (string) $request->getUri());
        $this->assertSame('{"name":"analyze.py","args":{"temperature":25}}', (string) $request->getBody());
    }

    public function testConnectionErrorBecomesImproperAction(): void
    {
        $mock = new MockHandler(array(new ConnectException('Connection refused', new Request('POST', 'http://127.0.0.1:8765'))));
        $service = new ScriptRunnerService($this->getClient($mock), 'http://127.0.0.1:8765');
        $this->expectException(ImproperActionException::class);
        $this->expectExceptionMessage('Error connecting to script runner');
        $service->run('analyze.py');
    }

    public function testRunnerErrorBecomesImproperAction(): void
    {
        $mock = new MockHandler(array(new Response(500, array(), 'boom')));
        $service = new ScriptRunnerService($this->getClient($mock), 'http://127.0.0.1:8765');
        $this->expectException(ImproperActionException::class);
        $this->expectExceptionMessage('Script runner returned an error (500)');
        $service->run('analyze.py');
    }

    public function testUnexpectedResponseBodyThrows(): void
    {
        $mock = new MockHandler(array(new Response(200, array(), 'not json')));
        $service = new ScriptRunnerService($this->getClient($mock), 'http://127.0.0.1:8765');
        $this->expectException(ImproperActionException::class);
        $this->expectExceptionMessage('Unexpected response from script runner');
        $service->run('analyze.py');
    }

    public function testSetRunnerUrl(): void
    {
        $mock = new MockHandler(array(new Response(200, array(), '{"output": "ok"}')));
        $service = new ScriptRunnerService($this->getClient($mock));
        $service->setRunnerUrl('http://127.0.0.1:9999');
        $this->assertSame('ok', $service->run('analyze.py'));
        $this->assertSame('http://127.0.0.1:9999', (string) $mock->getLastRequest()->getUri());
    }

    private function getClient(MockHandler $mock): Client
    {
        return new Client(array('handler' => HandlerStack::create($mock)));
    }
}
