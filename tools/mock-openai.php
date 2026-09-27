<?php

declare(strict_types=1);

// A fake OpenAI-compatible /chat/completions endpoint for smoke-testing and
// the Upstream integration tests.
// Usage:  php -S 127.0.0.1:8787 tools/mock-openai.php
// Returns a short SSE stream mimicking DeepSeek/Qwen/Ollama.
//
// Test hooks (query params, active on the /chat/completions path only):
//   ?status=302|404|500   reply with that status instead of streaming
//   ?sleep=1500           hold the connection for N ms before streaming
//   ?stall=1500           send one delta, go quiet for N ms, then finish

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);

if ($path !== '/v1/chat/completions') {
    http_response_code(404);
    exit('not found');
}

if (($q['status'] ?? '') !== '') {
    http_response_code((int) $q['status']);
    exit('error body');
}

if (($q['sleep'] ?? '') !== '') {
    usleep((int) $q['sleep'] * 1000);
}

// Echo request metadata so the request path is verifiable.
$input = json_decode((string) file_get_contents('php://input'), true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

if (($input['model'] ?? '') === '') {
    echo "data: {\"error\":{\"message\":\"missing model\"}}\n\n";
    exit;
}

// Mid-stream silence: one delta, then nothing for N ms, then the end marker.
if (($q['stall'] ?? '') !== '') {
    echo 'data: ' . json_encode(
        ['choices' => [['delta' => ['content' => 'first']]]],
        JSON_UNESCAPED_UNICODE,
    ) . "\n\n";
    flush();
    usleep((int) $q['stall'] * 1000);
    echo "data: [DONE]\n\n";
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