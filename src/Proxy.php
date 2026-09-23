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
    public const VERSION = '0.1.0';

    /**
     * @param float $heartbeatSeconds Emit a `: ping` comment at most this often.
     */
    public function __construct(
        private Emitter $emitter,
        private float $heartbeatSeconds = 20.0,
    ) {
    }

    /**
     * Relay a chat completion as SSE stream.
     *
     * Runs synchronously; throws when the client disconnect is not detected
     * but the underlying call failed. On client abort the relay simply ends.
     */
    public function stream(ClientInterface $client, array $messages, array $extraHeaders = []): void
    {
        $this->emitter->start($extraHeaders);

        $lastBeat = microtime(true);
        try {
            $client->chat($messages, function (string $delta) use (&$lastBeat): void {
                if (connection_aborted()) {
                    throw new AiProxyException('Client aborted the stream', 499);
                }
                $this->heartbeatIfDue($lastBeat);
                $this->emitter->send($delta);
            });

            // Signal end of stream with a sentinel SSE event the frontend can key on.
            $this->emitter->send('[DONE]', 'done');
        } finally {
            $this->emitter->close();
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