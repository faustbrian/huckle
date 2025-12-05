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
 * Exception thrown when a required file does not exist.
 *
 * Indicates attempts to read, parse, or access a file that is not present in
 * the filesystem. Commonly occurs when loading credential configuration files
 * or encrypted data stores.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileNotFoundException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a specific file path.
     *
     * @param string $filepath Absolute or relative path to the file that was not found
     *
     * @return self Configured exception instance with formatted error message
     */
    public static function forPath(string $filepath): self
    {
        return new self(sprintf('File not found: "%s"', $filepath));
    }
}
