<?php

declare(strict_types=1);

namespace MiGears\AiProxy;

use MiGears\AiProxy\Model\ClientInterface;
use MiGears\AiProxy\Stream\Emitter;

/**
 * Orchestrates the relay: pulls text deltas from a model client and pushes
 * them to an SSE emitter, injecting heartbeat comments and aborting the
 * upstream when the browser disconnects.
 */
final class Proxy
{
    public const VERSION = '2.0.0';

    /** @var callable():bool */
    private $isAborted;

    /**
     * @param float $heartbeatSeconds Emit a `: ping` comment at most this often.
     * @param callable():bool|null $isAborted Overrides the client-disconnect check (used for testing).
     */
    public function __construct(
        private Emitter $emitter,
        private float $heartbeatSeconds = 20.0,
        ?callable $isAborted = null,
    ) {
        $this->isAborted = $isAborted ?? static fn (): bool => connection_aborted() !== 0;
    }

    /**
     * Relay a chat completion as SSE stream.
     *
     * Runs synchronously; throws AiProxyException when the browser disconnects
     * (code 499) or the underlying call fails.
     *
     * @param array<int,array{role:string,content:string}> $messages     Chat history.
     * @param array<string,string> $extraHeaders Additional SSE headers to send.
     */
    public function stream(ClientInterface $client, array $messages, array $extraHeaders = []): void
    {
        $this->emitter->start($extraHeaders);

        $lastBeat = microtime(true);
        try {
            $client->chat(
                $messages,
                function (string $delta) use (&$lastBeat): void {
                    $this->abortIfClientGone();
                    $this->heartbeatIfDue($lastBeat);
                    $this->emitter->send($delta);
                },
                function () use (&$lastBeat): void {
                    // Checked here too: while the upstream is silent this is the
                    // only tick there is, so without it a disconnect would go
                    // unnoticed until the next delta — which may never arrive.
                    $this->abortIfClientGone();
                    $this->heartbeatIfDue($lastBeat);
                },
            );

            // Signal end of stream with a sentinel SSE event the frontend can key on.
            $this->emitter->send('[DONE]', 'done');
        } finally {
            $this->emitter->close();
        }
    }

    private function abortIfClientGone(): void
    {
        if (($this->isAborted)()) {
            throw new AiProxyException('Client aborted the stream', 499);
        }
    }

    private function heartbeatIfDue(float &$lastBeat): void
    {
        $now = microtime(true);
        if ($now - $lastBeat >= $this->heartbeatSeconds) {
            $this->emitter->comment('ping');
            $lastBeat = $now;
        }
    }
}