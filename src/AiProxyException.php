<?php

declare(strict_types=1);

namespace MiGears\AiProxy;

use RuntimeException;

class AiProxyException extends RuntimeException
{
    public const VERSION = '2.0.0';

    /**
     * Wrap a source exception (e.g. a cURL failure) preserving message and code.
     */
    public static function from(\Throwable $e): self
    {
        return new self($e->getMessage(), (int) $e->getCode(), $e);
    }
}