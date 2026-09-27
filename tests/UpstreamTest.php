<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Tests;

use MiGears\AiProxy\AiProxyException;
use MiGears\AiProxy\Stream\Upstream;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests against the local mock server (tools/mock-openai.php),
 * covering cURL option merging, status-code decisions, the idle heartbeat and
 * the idle watchdog.
 */
final class UpstreamTest extends TestCase
{
    private static int $port = 0;

    /** @var resource|null */
    private static $server;

    public static function setUpBeforeClass(): void
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::$port = (int) substr(strrchr((string) stream_socket_get_name($sock, false), ':') ?: '', 1);
        fclose($sock);

        $router = __DIR__ . '/../tools/mock-openai.php';
        $cmd = ['php', '-S', '127.0.0.1:' . self::$port, $router];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'a'],
            2 => ['file', '/dev/null', 'a'],
        ];
        // NOTE: PHP_CLI_SERVER_WORKERS is deliberately not used here — with
        // worker processes, SIGTERM to the master leaves orphaned workers
        // listening on the port after the suite ends.
        self::$server = proc_open($cmd, $descriptors, $pipes);
        static::assertIsResource(self::$server);

        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen('127.0.0.1', self::$port);
            if ($conn !== false) {
                fclose($conn);
                return;
            }
            usleep(20 * 1000);
        }
        static::fail('Mock server did not start in time');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        self::$server = null;
    }

    protected function tearDown(): void
    {
        // The built-in server is single-threaded: a test that abandons a request
        // leaves it busy until that request finishes. Wait for it to come back so
        // the next test measures its own stall and not a request queued behind one.
        $deadline = microtime(true) + 5.0;
        do {
            $started = microtime(true);
            try {
                Upstream::post($this->endpoint(), json_encode(['model' => 'm']), [], null, [CURLOPT_TIMEOUT => 6], null, 0.0);
            } catch (AiProxyException) {
                // Only the round-trip time matters; a slow reply just means retry.
            }
        } while (microtime(true) - $started > 0.5 && microtime(true) < $deadline);
    }

    private function endpoint(string $query = ''): string
    {
        return 'http://127.0.0.1:' . self::$port . '/v1/chat/completions' . $query;
    }

    public function testRelaysStreamedChunksFromTwoHundred(): void
    {
        $chunks = [];
        Upstream::post($this->endpoint(), json_encode(['model' => 'm']), [], function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        });
        $this->assertStringContainsString('Mock', implode('', $chunks));
    }

    public function testThrowsOnThreeHundred(): void
    {
        $this->expectException(AiProxyException::class);
        $this->expectExceptionCode(302);
        Upstream::post($this->endpoint('?status=302'), '{}', []);
    }

    public function testThrowsOnServerErrorAndBodiesIt(): void
    {
        try {
            Upstream::post($this->endpoint('?status=500'), '{}', []);
            static::fail('Expected AiProxyException');
        } catch (AiProxyException $e) {
            $this->assertSame(500, $e->getCode());
            $this->assertStringContainsString('error body', $e->getMessage());
        }
    }

    public function testCallerTimeoutOverrideWins(): void
    {
        $this->expectException(AiProxyException::class);
        $this->expectExceptionCode(CURLE_OPERATION_TIMEDOUT);
        Upstream::post($this->endpoint('?sleep=1500'), '{}', [], null, [CURLOPT_TIMEOUT => 1]);
    }

    public function testIdleCallbackFiresDuringSilentUpstream(): void
    {
        $idleCalls = 0;
        Upstream::post(
            $this->endpoint('?sleep=2000'),
            '{}',
            [],
            static function (string $chunk): void {
            },
            [],
            function () use (&$idleCalls): void {
                $idleCalls++;
            },
        );
        $this->assertGreaterThanOrEqual(1, $idleCalls);
    }

    public function testAbortsWhenUpstreamGoesSilentBeyondIdleTimeout(): void
    {
        try {
            Upstream::post(
                $this->endpoint('?stall=1500'),
                json_encode(['model' => 'm']),
                [],
                null,
                [],
                null,
                0.5,
            );
            static::fail('Expected AiProxyException');
        } catch (AiProxyException $e) {
            $this->assertSame(CURLE_ABORTED_BY_CALLBACK, $e->getCode());
            $this->assertStringContainsString('idle timeout', $e->getMessage());
        }
    }

    public function testIdleWatchdogIsOffWhenTimeoutIsZero(): void
    {
        $output = '';
        Upstream::post(
            $this->endpoint('?stall=1200'),
            json_encode(['model' => 'm']),
            [],
            function (string $chunk) use (&$output): void {
                $output .= $chunk;
            },
            [],
            null,
            0.0,
        );
        // The stall outlasts the 0.5s used above; with the watchdog disabled the
        // transfer must survive to the end marker.
        $this->assertStringContainsString('[DONE]', $output);
    }

    public function testDefaultIdleTimeoutIsBounded(): void
    {
        // The point of the watchdog: by default a stalled upstream must not be
        // able to hold the calling process forever (0 would mean "no bound").
        $this->assertGreaterThan(0.0, Upstream::DEFAULT_IDLE_TIMEOUT);
    }
}
