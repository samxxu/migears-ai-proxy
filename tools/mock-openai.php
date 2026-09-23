<?php

declare(strict_types=1);

// A fake OpenAI-compatible /chat/completions endpoint for smoke-testing.
// Usage:  php -S 127.0.0.1:8787 tools/mock-openai.php
// Returns a short SSE stream mimicking DeepSeek/Qwen/Ollama.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path !== '/v1/chat/completions') {
    http_response_code(404);
    exit('not found');
}

// Echo request metadata so the request path is verifiable.
$input = json_decode((string) file_get_contents('php://input'), true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

if (($input['model'] ?? '') === '') {
    echo "data: {\"error\":{\"message\":\"missing model\"}}\n\n";
    exit;
}

$tokens = ['Mock ', '答复 ', '完成。'];
foreach ($tokens as $t) {
    echo 'data: ' . json_encode(
        ['choices' => [['delta' => ['content' => $t]]]],
        JSON_UNESCAPED_UNICODE,
    ) . "\n\n";
    flush();
    usleep(30 * 1000);
}
echo "data: [DONE]\n\n";