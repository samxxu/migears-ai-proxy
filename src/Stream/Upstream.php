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
    /** Cap on buffered error bodies so a hostile upstream cannot exhaust memory. */
    private const MAX_ERROR_BODY = 1 << 16;

    /**
     * Send a POST request whose body streams back chunk by chunk.
     *
     * @param string|\Stringable $jsonBody      Pre-encoded request body.
     * @param array<string,string> $headers      Extra headers (e.g. Authorization).
     * @param callable(string):void $onChunk     Invoked with each raw body chunk.
     * @param array<string,mixed> $extraCurlOpts Additional cURL options to merge (caller wins).
     * @param callable():void|null $onIdle       Invoked periodically even without body chunks (heartbeat).
     *
     * @throws AiProxyException On transport error or non-2xx upstream status.
     */
    public static function post(
        string $url,
        string|\Stringable $jsonBody,
        array $headers = [],
        ?callable $onChunk = null,
        array $extraCurlOpts = [],
        ?callable $onIdle = null,
    ): void {
        $channel = curl_init($url);
        if ($channel === false) {
            throw new AiProxyException('Failed to initialize cURL for upstream: ' . $url);
        }

        $status = 0;
        $errorBody = '';
        $writer = static function ($ch, string $chunk) use (&$status, &$errorBody, $onChunk): int {
            $len = strlen($chunk);
            // $status 由下方 $headerFn 闭包通过 use (&$status) 写入，PHPStan 追不到
            // 跨闭包的引用赋值，这里显式扩宽为 int（第 86 行处在闭包外，无需处理）。
            /** @var int $status Set by the $headerFn callback below via by-reference capture. */
            // Buffer anything but a 2xx body instead of relaying it to the client.
            if ($status !== 0 && ($status < 200 || $status >= 300)) {
                if (strlen($errorBody) < self::MAX_ERROR_BODY) {
                    $errorBody .= substr($chunk, 0, self::MAX_ERROR_BODY - strlen($errorBody));
                }
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
        ];
        if ($onIdle !== null) {
            // libcurl calls the progress function roughly once per second even
            // while the body is silent, which is what drives idle heartbeats.
            $options[CURLOPT_NOPROGRESS] = false;
            $options[CURLOPT_PROGRESSFUNCTION] = static function ($ch, int $dltotal, int $dlnow, int $ultotal, int $ulnow) use ($onIdle): int {
                $onIdle();
                return 0;
            };
        }
        $options = array_replace($options, $extraCurlOpts); // caller overrides win

        if (!curl_setopt_array($channel, $options)) {
            throw new AiProxyException('Failed to apply cURL options for upstream: ' . $url);
        }

        $result = curl_exec($channel);
        $errno = curl_errno($channel);
        $error = curl_error($channel);

        if ($result === false || $errno !== 0) {
            throw new AiProxyException(
                sprintf('Upstream transport error (code %d): %s', $errno, $error !== '' ? $error : 'cURL transfer failed'),
                $errno,
            );
        }
        if ($status < 200 || $status >= 300) {
            throw new AiProxyException(
                sprintf('Upstream returned HTTP %d: %s', $status, trim($errorBody)),
                $status,
            );
        }
    }
}