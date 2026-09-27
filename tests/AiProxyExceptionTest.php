<?php

declare(strict_types=1);

namespace MiGears\AiProxy\Tests;

use MiGears\AiProxy\AiProxyException;
use PHPUnit\Framework\TestCase;

final class AiProxyExceptionTest extends TestCase
{
    public function testFromWrapsASourceExceptionPreservingMessageCodeAndCause(): void
    {
        $source = new \RuntimeException('connection reset', 7);

        $wrapped = AiProxyException::from($source);

        self::assertSame('connection reset', $wrapped->getMessage());
        self::assertSame(7, $wrapped->getCode());
        self::assertSame($source, $wrapped->getPrevious());
        self::assertInstanceOf(AiProxyException::class, $wrapped);
    }

    public function testVersionConstant(): void
    {
        self::assertSame('2.0.0', AiProxyException::VERSION);
    }
}
