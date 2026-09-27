<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Stream;

use MiGears\AiProxy\AiProxyException;

/**
 * Server-Sent Events output sink.
 *
 * Emits SSE-framed messages (`data:`, `event:`, `id:`) to a writer and
 * flushes after each frame so the browser receives chunks as they arrive.
 */
final class Emitter
{
    /** @var callable(string):bool */
    private $write;

    /** @var callable():void */
    private $flush;

    /** @var callable(string):void */
    private $sendHeader;

    /** @var resource|null */
    private $stream;

    private bool $started = false;

    private int $baseBufferLevel;

    /**
     * @param callable(string):bool|null $write      Custom sink; defaults to php://output.
     * @param callable():void|null       $flush      Custom flusher; defaults to ob_flush()+flush().
     * @param callable(string):void|null $sendHeader Custom header sender; defaults to header().
     */
    public function __construct(?callable $write = null, ?callable $flush = null, ?callable $sendHeader = null)
    {
        $this->baseBufferLevel = ob_get_level();
        // php://output works in both CLI and web SAPIs, unlike the CLI-only STDOUT.
        $this->stream = $write === null ? (fopen('php://output', 'wb') ?: null) : null;
        $this->write = $write ?? fn (string $chunk): bool => $this->stream !== null && fwrite($this->stream, $chunk) !== false;
        $this->flush = $flush ?? static function (): void {
            if (ob_get_level() > 0 && function_exists('ob_flush')) {
                ob_flush();
            }
            flush();
        };
        $this->sendHeader = $sendHeader ?? static function (string $header): void {
            header($header);
        };
    }

    /**
     * Begin the event stream: declare headers and clear output buffering.
     *
     * @param array<string,string> $extraHeaders Additional headers to send (overrides defaults).
     */
    public function start(array $extraHeaders = []): void
    {
        if ($this->started) {
            throw new AiProxyException('Emitter already started');
        }
        $this->started = true;

        $headers = array_merge(
            ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no'],
            $extraHeaders,
        );
        foreach ($headers as $name => $value) {
            ($this->sendHeader)(sprintf('%s: %s', $name, $value));
        }

        while (ob_get_level() > $this->baseBufferLevel) {
            ob_end_flush();
        }
        $this->flushLevel();
    }

    /**
     * Emit a single SSE message.
     *
     * @param string            $data  Payload (may contain newlines).
     * @param string|null       $event Optional `event:` name.
     * @param string|int|null   $id    Optional `id:` value.
     */
    public function send(string $data, ?string $event = null, string|int|null $id = null): void
    {
        $this->assertStarted();
        if ($id !== null) {
            $this->line("id: {$id}");
        }
        if ($event !== null) {
            $this->line("event: {$event}");
        }
        foreach (explode("\n", $data) as $line) {
            $this->line("data: {$line}");
        }
        $this->line('');
    }

    /**
     * Emit a heartbeat comment (`: ...`) to keep the connection alive.
     */
    public function comment(string $message): void
    {
        $this->assertStarted();
        $this->line(': ' . str_replace(["\r", "\n"], ' ', $message));
    }

    /**
     * Final flush; closes the stream from the caller's perspective.
     */
    public function close(): void
    {
        $this->flushLevel();
    }

    private function assertStarted(): void
    {
        if (!$this->started) {
            throw new AiProxyException('Emitter::start() must be called before emitting');
        }
    }

    private function line(string $line): void
    {
        ($this->write)($line . "\n");
        $this->flushLevel();
    }

    private function flushLevel(): void
    {
        ($this->flush)();
    }
}