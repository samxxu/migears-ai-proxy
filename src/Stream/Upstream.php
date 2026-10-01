<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Stream;

use MiGears\AiProxy\AiProxyException;

/**
 * Upstream reader backed by cURL.
 *
 * Streams the response body chunk-by-chunk through a callback via
 * `CURLOPT_WRITEFUNCTION`, so the relay never buffers the full payload.
 * A transfer that goes quiet is aborted, so a silent upstream cannot hold
 * the calling process forever. Only silence is bounded: total time stays
 * unbounded by design, so an upstream that trickles one byte per idle
 * window is not cut off.
 */
final class Upstream
{
    /** Cap on buffered error bodies so a hostile upstream cannot exhaust memory. */
    private const MAX_ERROR_BODY = 1 << 16;

    /** Seconds without a body byte after which the transfer is aborted. */
    public const DEFAULT_IDLE_TIMEOUT = 300.0;

    /**
     * Send a POST request whose body streams back chunk by chunk.
     *
     * `CURLOPT_TIMEOUT` stays 0: a long generation is legitimate and must not
     * hit a total cap. What is bounded instead is silence — no body byte for
     * $idleTimeoutSeconds aborts the transfer. Passing CURLOPT_NOPROGRESS or
     * CURLOPT_PROGRESSFUNCTION in $extraCurlOpts replaces the internal tick,
     * which disables both the idle watchdog and the $onIdle heartbeat.
     *
     * @param string|\Stringable   $jsonBody          Pre-encoded request body.
     * @param list<string>         $headers           Raw header lines ("Content-Type: ...").
     * @param callable(string):void $onChunk          Invoked with each raw body chunk.
     * @param array<string,mixed>  $extraCurlOpts     Additional cURL options to merge (caller wins).
     * @param callable():void|null $onIdle            Invoked on each transfer tick, even without body chunks (heartbeat).
     * @param float                $idleTimeoutSeconds Abort after this much silence; 0 disables the watchdog.
     *
     * @throws AiProxyException On transport error, idle timeout or non-2xx upstream status.
     */
    public static function post(
        string $url,
        string|\Stringable $jsonBody,
        array $headers = [],
        ?callable $onChunk = null,
        array $extraCurlOpts = [],
        ?callable $onIdle = null,
        float $idleTimeoutSeconds = self::DEFAULT_IDLE_TIMEOUT,
    ): void {
        $channel = curl_init($url);
        if ($channel === false) {
            throw new AiProxyException('Failed to initialize cURL for upstream: ' . $url);
        }

        $status = 0;
        $errorBody = '';
        $lastByteAt = microtime(true);
        $writer = static function ($ch, string $chunk) use (&$status, &$errorBody, &$lastByteAt, $onChunk): int {
            $len = strlen($chunk);
            $lastByteAt = microtime(true);
            // $status 由下方 $headerFn 闭包通过 use (&$status) 写入，PHPStan 追不到
            // 跨闭包的引用赋值，这里显式扩宽为 int（闭包以外的使用处无需处理）。
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
        // libcurl ticks the progress function in a short burst up front and
        // then about once per second — the only wake-up we get while the body
        // is silent. One tick does both jobs: it feeds the caller's heartbeat
        // and it aborts a transfer whose upstream has gone quiet.
        $options[CURLOPT_NOPROGRESS] = false;
        $options[CURLOPT_PROGRESSFUNCTION] = static function ($ch, int $dltotal, int $dlnow, int $ultotal, int $ulnow) use (&$lastByteAt, $onIdle, $idleTimeoutSeconds): int {
            if ($idleTimeoutSeconds > 0 && microtime(true) - $lastByteAt >= $idleTimeoutSeconds) {
                return 1; // non-zero aborts the transfer (CURLE_ABORTED_BY_CALLBACK)
            }
            if ($onIdle !== null) {
                $onIdle();
            }
            return 0;
        };
        $options = array_replace($options, $extraCurlOpts); // caller overrides win

        if (!curl_setopt_array($channel, $options)) {
            throw new AiProxyException('Failed to apply cURL options for upstream: ' . $url);
        }

        $result = curl_exec($channel);
        $errno = curl_errno($channel);
        $error = curl_error($channel);

        if ($errno === CURLE_ABORTED_BY_CALLBACK) {
            throw new AiProxyException(
                sprintf('Upstream idle timeout: no body bytes for %g s', $idleTimeoutSeconds),
                $errno,
            );
        }
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