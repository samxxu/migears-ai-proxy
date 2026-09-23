<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Model;

/**
 * Contract for any OpenAI-compatible chat completion client.
 */
interface ClientInterface
{
    /**
     * Stream a chat completion.
     *
     * @param array<int,array{role:string,content:string}> $messages  Chat history.
     * @param callable(string):void $onChunk  Invoked with each text fragment as it arrives.
     *
     * @throws \MiGears\AiProxy\AiProxyException On upstream failure.
     */
    public function chat(array $messages, callable $onChunk): void;
}