<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Tests;

use MiGears\AiProxy\AiProxyException;
use MiGears\AiProxy\Model\ClientInterface;
use MiGears\AiProxy\Proxy;
use MiGears\AiProxy\Stream\Emitter;
use PHPUnit\Framework\TestCase;

final class ProxyTest extends TestCase
{
    /** @var list<string> */
    private array $output = [];

    /** @var list<string> */
    private array $headers = [];

    private function makeEmitter(): Emitter
    {
        return new Emitter(
            write: function (string $chunk): bool {
                $this->output[] = $chunk;
                return true;
            },
            flush: static function (): void {
            },
            sendHeader: function (string $header): void {
                $this->headers[] = $header;
            },
        );
    }

    private function makeProxy(ClientInterface $client, float $heartbeat = 20.0, ?callable $isAborted = null): Proxy
    {
        return new Proxy($this->makeEmitter(), $heartbeat, $isAborted);
    }

    private function joined(): string
    {
        return implode('', $this->output);
    }

    public function testRelaysDeltasAndEndsWithDoneEvent(): void
    {
        $client = new class implements ClientInterface {
            public function chat(array $messages, callable $onChunk, ?callable $onIdle = null): void
            {
                $onChunk('Hello ');
                $onChunk('World');
            }
        };

        $this->makeProxy($client)->stream($client, []);
        $this->assertSame(
            "data: Hello \n\ndata: World\n\nevent: done\ndata: [DONE]\n\n",
            $this->joined(),
        );
    }

    public function testSendsHeartbeatWhenIdleBeyondThreshold(): void
    {
        $client = new class implements ClientInterface {
            public function chat(array $messages, callable $onChunk, ?callable $onIdle = null): void
            {
                $onChunk('start');
                usleep(30 * 1000); // simulate a gap longer than a 10ms threshold
                $onChunk('end');
            }
        };

        $this->makeProxy($client, 0.01)->stream($client, []);
        $this->assertStringContainsString(': ping', $this->joined());
    }

    public function testSendsHeartbeatDuringChunkFreeStretch(): void
    {
        // A truly silent upstream: idle ticks but no deltas for 30ms, then a
        // single trailing chunk. Heartbeat must come from the idle callback.
        $client = new class implements ClientInterface {
            public function chat(array $messages, callable $onChunk, ?callable $onIdle = null): void
            {
                $onIdle();
                usleep(30 * 1000);
                $onIdle();
                $onChunk('end');
            }
        };

        $this->makeProxy($client, 0.01)->stream($client, []);
        $this->assertStringContainsString(': ping', $this->joined());
    }

    public function testSendsHeadersOnStart(): void
    {
        $client = new class implements ClientInterface {
            public function chat(array $messages, callable $onChunk, ?callable $onIdle = null): void
            {
                $onChunk('[DONE]');
            }
        };

        $this->makeProxy($client)->stream($client, [], ['Custom' => 'v']);
        $this->assertContains('Content-Type: text/event-stream', $this->headers);
        $this->assertContains('Custom: v', $this->headers);
    }

    public function testStopsWhenClientDisconnectsBeforeADelta(): void
    {
        $client = new class implements ClientInterface {
            public function chat(array $messages, callable $onChunk, ?callable $onIdle = null): void
            {
                $onChunk('never relayed');
            }
        };

        try {
            $this->makeProxy($client, 20.0, static fn (): bool => true)->stream($client, []);
            static::fail('Expected AiProxyException');
        } catch (AiProxyException $e) {
            $this->assertSame(499, $e->getCode());
        }
        $this->assertStringNotContainsString('never relayed', $this->joined());
        $this->assertStringNotContainsString('[DONE]', $this->joined());
    }

    public function testStopsWhenClientDisconnectsDuringAnIdlePeriod(): void
    {
        $gone = new class {
            public bool $value = false;
        };
        $client = new class($gone) implements ClientInterface {
            public function __construct(private object $state)
            {
            }

            public function chat(array $messages, callable $onChunk, ?callable $onIdle = null): void
            {
                $onChunk('hello');
                $onIdle();                  // still connected
                $this->state->value = true; // the browser goes away mid-silence
                $onIdle();                  // no delta follows: the idle tick must notice
                $onChunk('must not be relayed');
            }
        };

        try {
            $this->makeProxy($client, 20.0, static fn (): bool => $gone->value)->stream($client, []);
            static::fail('Expected AiProxyException');
        } catch (AiProxyException $e) {
            $this->assertSame(499, $e->getCode());
        }
        $this->assertStringContainsString('hello', $this->joined());
        $this->assertStringNotContainsString('must not be relayed', $this->joined());
    }
}