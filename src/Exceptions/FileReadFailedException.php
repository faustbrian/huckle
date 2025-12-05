<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when file_get_contents operation fails.
 *
 * Indicates failures during the actual file content reading process, distinct from
 * permission or existence issues. May occur due to I/O errors, filesystem corruption,
 * or interrupted read operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileReadFailedException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a file read operation failure.
     *
     * @param string $path Absolute or relative path to the file that failed to read
     *
     * @return self Configured exception instance with formatted error message
     */
    public static function atPath(string $path): self
    {
        return new self(sprintf('Failed to read file: %s', $path));
    }
}
