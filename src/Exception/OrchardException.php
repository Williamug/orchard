<?php

declare(strict_types=1);

namespace Orchard\Exception;

class OrchardException extends \RuntimeException
{
    public static function invalidPath(string $path): self
    {
        return new self(sprintf('Path does not exist or is not a directory: %s', $path));
    }

    public static function invalidConfig(string $message): self
    {
        return new self(sprintf('Configuration error: %s', $message));
    }

    public static function invalidParallel(int $max): self
    {
        return new self(sprintf('Parallel value must be between 1 and %d (CPU cores × 2).', $max));
    }
}
