<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Stream;

use MiGears\AiProxy\AiProxyException;

/**
 * Upstream reader backed by cURL.
 *
 * Streams the response body chunk-by-chunk through a callback via
 * `CURLOPT_WRITEFUNCTION`, so the relay never buffers the full payload.
 */
final class Upstream
{
    /**
     * Send a POST request whose body streams back chunk by chunk.
     *
     * @param string|\Stringable $jsonBody      Pre-encoded request body.
     * @param array<string,string> $headers      Extra headers (e.g. Authorization).
     * @param callable(string):void $onChunk     Invoked with each raw body chunk.
     * @param array<string,mixed> $extraCurlOpts Additional cURL options to merge.
     *
     * @throws AiProxyException On transport error or non-2xx upstream status.
     */
    public static function post(
        string $url,
        string|\Stringable $jsonBody,
        array $headers = [],
        ?callable $onChunk = null,
        array $extraCurlOpts = [],
    ): void {
        $channel = curl_init($url);
        if ($channel === false) {
            throw new AiProxyException('Failed to initialize cURL for upstream: ' . $url);
        }

        $status = 0;
        $errorBody = '';
        $writer = static function ($ch, string $chunk) use (&$status, &$errorBody, $onChunk): int {
            $len = strlen($chunk);
            // If the upstream replied with an error, buffer instead of relaying it.
            if ($status >= 400) {
                $errorBody .= $chunk;
                return $len;
            }
            if ($onChunk !== null) {
                $onChunk($chunk);
            }
            return $len;
        };
        $headerFn = static function ($ch, string $line) use (&$status): int {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
            return strlen($line);
        };

        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => (string) $jsonBody,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => $writer,
            CURLOPT_HEADERFUNCTION => $headerFn,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 0, // streaming: cap by the caller via CURLOPT_TIMEOUT override
        ] + $extraCurlOpts;

        if (!curl_setopt_array($channel, $options)) {
            throw new AiProxyException('Failed to apply cURL options for upstream: ' . $url);
        }

        curl_exec($channel);
        $errno = curl_errno($channel);
        $error = curl_error($channel);

        if ($errno !== 0) {
            throw new AiProxyException(
                sprintf('Upstream transport error (code %d): %s', $errno, $error),
                $errno,
            );
        }
        if ($status >= 400) {
            throw new AiProxyException(
                sprintf('Upstream returned HTTP %d: %s', $status, trim($errorBody)),
                $status,
            );
        }
    }
}