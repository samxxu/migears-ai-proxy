<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Tests;

use MiGears\AiProxy\Model\OpenAiClient;
use PHPUnit\Framework\TestCase;

final class OpenAiClientTest extends TestCase
{
    public function testParsesDeltasAcrossChunkBoundaries(): void
    {
        $transport = function (string $url, string $body, array $headers, callable $onChunk, array $opts): void {
            // A JSON event straddles a network chunk boundary: only when the
            // terminating newline arrives does the buffer yield the delta.
            $onChunk("data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"");
            $onChunk("}}]}\ndata: " . json_encode(['choices' => [['delta' => ['content' => '!']]]]) . "\n\n");
            $onChunk("data: [DONE]\n");
        };

        $client = new OpenAiClient('https://x/v1/chat/completions', 'tok', 'm', transport: $transport);
        $chunks = [];
        $client->chat([['role' => 'user', 'content' => 'hi']], function (string $t) use (&$chunks): void {
            $chunks[] = $t;
        });

        $this->assertSame(['Hello', '!'], $chunks);
    }

    public function testIgnoresNonDataAndEmptyDeltas(): void
    {
        $transport = function (string $url, string $body, array $headers, callable $onChunk, array $opts): void {
            $onChunk(": comment\n");
            $onChunk("data: " . json_encode(['choices' => [['delta' => ['content' => '']]]]) . "\n\n");
            $onChunk("data: " . json_encode(['choices' => [['delta' => ['content' => 'ok']]]]) . "\n");
        };

        $client = new OpenAiClient('https://x/v1/chat/completions', 'tok', 'm', transport: $transport);
        $chunks = [];
        $client->chat([], function (string $t) use (&$chunks): void {
            $chunks[] = $t;
        });

        $this->assertSame(['ok'], $chunks);
    }

    public function testDropsWhitespaceOnlyDeltas(): void
    {
        $transport = function (string $url, string $body, array $headers, callable $onChunk, array $opts): void {
            $onChunk("data: " . json_encode(['choices' => [['delta' => ['content' => "\n\n"]]]]) . "\n");
            $onChunk("data: " . json_encode(['choices' => [['delta' => ['content' => "keep\n\nsub"]]]]) . "\n");
        };

        $client = new OpenAiClient('https://x/v1/chat/completions', 'tok', 'm', transport: $transport);
        $chunks = [];
        $client->chat([], function (string $t) use (&$chunks): void {
            $chunks[] = $t;
        });

        // Pure-newline delta dropped; meaningful partial newlines preserved.
        $this->assertSame(["keep\n\nsub"], $chunks);
    }

    public function testBuildsBodyAndHeadersWithBearerToken(): void
    {
        $seen = [];
        $transport = function (string $url, string $body, array $headers, callable $onChunk, array $opts) use (&$seen): void {
            $seen = [$url, json_decode($body, true), $headers];
            $onChunk("data: [DONE]\n");
        };

        $client = new OpenAiClient('https://deepseek/chat/completions', 'secret', 'deepseek-chat',
            extraBody: ['temperature' => 0.2], transport: $transport);
        $client->chat([['role' => 'user', 'content' => 'q']], static function (string $t): void {
        });

        $this->assertSame('https://deepseek/chat/completions', $seen[0]);
        $this->assertSame('deepseek-chat', $seen[1]['model']);
        $this->assertTrue($seen[1]['stream']);
        $this->assertSame(0.2, $seen[1]['temperature']);
        $this->assertSame([['role' => 'user', 'content' => 'q']], $seen[1]['messages']);
        $this->assertContains('Authorization: Bearer secret', $seen[2]);
    }
}