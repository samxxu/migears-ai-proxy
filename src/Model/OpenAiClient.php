<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Model;

use MiGears\AiProxy\AiProxyException;
use MiGears\AiProxy\Stream\Upstream;

/**
 * OpenAI-compatible streaming client.
 *
 * Works with any provider exposing the OpenAI `/chat/completions` SSE
 * contract (DeepSeek, Qwen, Ollama, Together, ...). Parses each SSE
 * `data:` line and forwards the text deltas to the callback.
 */
final class OpenAiClient implements ClientInterface
{
    public const VERSION = '2.0.0';

    private ?\Closure $transport;

    /**
     * @param string   $endpoint Chat completions URL.
     * @param string   $token    API key (Bearer token).
     * @param string   $model    Model identifier.
     * @param array<string,mixed> $extraBody Extra JSON body fields to merge (temperature, max_tokens, ...).
     * @param array<string,mixed> $curlOpts Extra cURL options (e.g. CURLOPT_TIMEOUT).
     * @param (callable(string,string,array<string,string>,callable(string):void,array<string,mixed>,callable():void|null):void)|null $transport
     *   Overrides the upstream transport (used for testing).
     */
    public function __construct(
        private string $endpoint,
        private string $token,
        private string $model,
        private array $extraBody = [],
        private array $curlOpts = [],
        ?callable $transport = null,
    ) {
        $this->transport = $transport === null ? null : \Closure::fromCallable($transport);
    }

    public function chat(array $messages, callable $onChunk, ?callable $onIdle = null): void
    {
        $body = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
        ], $this->extraBody);

        $transport = $this->transport ?? \Closure::fromCallable(Upstream::post(...));
        $buffer = '';

        $transport(
            $this->endpoint,
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'Authorization: Bearer ' . $this->token,
            ],
            function (string $chunk) use (&$buffer, $onChunk): void {
                $buffer .= $chunk;
                $this->drain($buffer, $onChunk);
            },
            $this->curlOpts,
            $onIdle,
        );

        // Flush any trailing line not terminated by a newline.
        if (trim($buffer) !== '') {
            $this->emitLine(trim($buffer), $onChunk);
        }
    }

    /**
     * Consume complete SSE lines from the buffer, forwarding text deltas.
     */
    private function drain(string &$buffer, callable $onChunk): void
    {
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            $line = rtrim($line, "\r");
            if ($line === '') {
                continue;
            }
            $this->emitLine($line, $onChunk);
        }
    }

    private function emitLine(string $line, callable $onChunk): void
    {
        if (!str_starts_with($line, 'data:')) {
            return; // ignore comments, event padders, etc.
        }
        $payload = trim(substr($line, 5));
        if ($payload === '' || $payload === '[DONE]') {
            return;
        }
        $json = json_decode($payload, true);
        if (!is_array($json)) {
            return;
        }
        $delta = $json['choices'][0]['delta']['content'] ?? $json['choices'][0]['text'] ?? null;
        // Drop pure-whitespace deltas (e.g. content "\n\n") which carry no
        // information but would produce noisy empty `data:` frames. Keep
        // meaningful partial newlines inside real text intact.
        if (is_string($delta) && trim($delta) !== '') {
            $onChunk($delta);
        }
    }
}