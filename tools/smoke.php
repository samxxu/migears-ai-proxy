<?php

declare(strict_types=1);

// Integration smoke test for migears/ai-proxy.
//
// Default:   hits the local mock server (tools/mock-openai.php)
// Optional:  real OpenAI-compatible endpoint via env:
//   AI_PROXY_ENDPOINT=https://api.deepseek.com/chat/completions
//   AI_PROXY_TOKEN=<your-secret-key>
//   AI_PROXY_MODEL=deepseek-chat
//
// Run:  php tools/smoke.php

require __DIR__ . '/../tests/bootstrap.php';

use MiGears\AiProxy\Proxy;
use MiGears\AiProxy\Stream\Emitter;
use MiGears\AiProxy\Model\OpenAiClient;

$endpoint = getenv('AI_PROXY_ENDPOINT') ?: 'http://127.0.0.1:8787/v1/chat/completions';
$token    = getenv('AI_PROXY_TOKEN') ?: 'mock-token';
$model    = getenv('AI_PROXY_MODEL') ?: 'mock-model';

echo "Target: $endpoint (model: {$model})\n";
echo "Streaming...\n\n";

$client = new OpenAiClient(
    endpoint: $endpoint,
    token:    $token,
    model:    $model,
    curlOpts: [CURLOPT_TIMEOUT => 10],
);

// Custom sink writes raw SSE frames to stdout so the smoke run is visible.
$output = '';
$emitter = new Emitter(
    write: function (string $chunk) use (&$output): bool {
        $output .= $chunk;
        echo $chunk;
        return true;
    },
    flush: static function (): void {
    },
    sendHeader: static function (string $header): void {
    },
);

$proxy = new Proxy($emitter, heartbeatSeconds: 1);
$proxy->stream($client, [
    ['role' => 'user', 'content' => '你好，请自我介绍一下。'],
]);

echo "\n\n--- smoke result ---\n";
// The relay forwards *plain text* deltas (OpenAiClient already decoded the
// upstream JSON), so each `data:` payload is directly the delta text.
$text = '';
foreach (explode("\n", $output) as $line) {
    if (str_starts_with($line, 'data: ') && trim(substr($line, 6)) !== '[DONE]') {
        $text .= trim(substr($line, 6));
    }
}
echo "Received text: " . ($text !== '' ? $text : '(empty — check upstream)') . "\n";
exit($text !== '' ? 0 : 1);