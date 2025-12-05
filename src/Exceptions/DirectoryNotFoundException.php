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
 * Exception thrown when a required directory does not exist.
 *
 * Indicates attempts to access, read, or scan a directory that is not present
 * in the filesystem. Commonly occurs during credential store initialization or
 * configuration directory lookups.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DirectoryNotFoundException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a specific directory path.
     *
     * @param string $path Absolute or relative path to the directory that was not found
     *
     * @return self Configured exception instance with formatted error message
     */
    public static function forPath(string $path): self
    {
        return new self(sprintf('Directory not found: "%s"', $path));
    }
}
