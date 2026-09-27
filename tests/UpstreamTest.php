<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Tests;

use MiGears\AiProxy\AiProxyException;
use MiGears\AiProxy\Stream\Upstream;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests against the local mock server (tools/mock-openai.php),
 * covering cURL option merging, status-code decisions and the idle heartbeat.
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
        Upstream::post($this->endpoint('?sleep=3000'), '{}', [], null, [CURLOPT_TIMEOUT => 1]);
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
}
