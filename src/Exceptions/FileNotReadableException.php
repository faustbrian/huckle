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
 * Exception thrown when a file exists but lacks sufficient read permissions.
 *
 * Indicates filesystem permission issues preventing file access. Commonly occurs
 * when the application process lacks the necessary read permissions for credential
 * files, configuration stores, or encrypted data.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileNotReadableException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a file that cannot be read.
     *
     * @param string $path Absolute or relative path to the unreadable file
     *
     * @return self Configured exception instance with formatted error message
     */
    public static function atPath(string $path): self
    {
        return new self(sprintf('File not readable: %s', $path));
    }
}
