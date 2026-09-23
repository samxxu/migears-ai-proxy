<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Tests;

use MiGears\AiProxy\AiProxyException;
use MiGears\AiProxy\Stream\Emitter;
use PHPUnit\Framework\TestCase;

final class EmitterTest extends TestCase
{
    /** @var list<string> */
    private array $output = [];

    private function makeEmitter(?array &$capturedHeaders = null): Emitter
    {
        $capturedHeaders = [];
        return new Emitter(
            write: function (string $chunk): bool {
                $this->output[] = $chunk;
                return true;
            },
            flush: function (): void {
                // no-op
            },
            sendHeader: function (string $header) use (&$capturedHeaders): void {
                $capturedHeaders[] = $header;
            },
        );
    }

    public function testStartEmitsDefaultAndExtraHeaders(): void
    {
        $headers = [];
        $e = $this->makeEmitter($headers);
        $e->start(['Custom' => 'x']);
        $this->assertContains('Content-Type: text/event-stream', $headers);
        $this->assertContains('Cache-Control: no-cache', $headers);
        $this->assertContains('Custom: x', $headers);
    }

    public function testDoubleStartThrows(): void
    {
        $e = $this->makeEmitter();
        $e->start();
        $this->expectException(AiProxyException::class);
        $e->start();
    }

    public function testSendFramesData(): void
    {
        $e = $this->makeEmitter();
        $e->start();
        $e->send('hello world');
        $this->assertSame("data: hello world\n\n", $this->joined());
    }

    public function testSendWithEventAndId(): void
    {
        $e = $this->makeEmitter();
        $e->start();
        $e->send('chunk', event: 'delta', id: 7);
        $this->assertSame("id: 7\nevent: delta\ndata: chunk\n\n", $this->joined());
    }

    public function testSendMultilineFramesEachLine(): void
    {
        $e = $this->makeEmitter();
        $e->start();
        $e->send("a\nb");
        $this->assertSame("data: a\ndata: b\n\n", $this->joined());
    }

    public function testCommentEmitsHeartbeatLine(): void
    {
        $e = $this->makeEmitter();
        $e->start();
        $e->comment('ping');
        $this->assertSame(": ping\n", $this->joined());
    }

    public function testGapAfterInterleavedSends(): void
    {
        $e = $this->makeEmitter();
        $e->start();
        $e->send('one');
        $e->comment('ping');
        $e->send('two');
        $this->assertSame("data: one\n\n: ping\ndata: two\n\n", $this->joined());
    }

    public function testEmitBeforeStartThrows(): void
    {
        $e = $this->makeEmitter();
        $this->expectException(AiProxyException::class);
        $e->send('nope');
    }

    private function joined(): string
    {
        return implode('', $this->output);
    }
}