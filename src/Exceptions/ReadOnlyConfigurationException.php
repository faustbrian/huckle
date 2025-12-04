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
 * Exception thrown when attempting to write to a read-only configuration file.
 *
 * This exception is raised when file operations fail due to permission issues,
 * missing files, or other I/O errors that prevent writing configuration data.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ReadOnlyConfigurationException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a specific file path.
     *
     * This static factory method creates a new exception instance with a message
     * identifying the specific file path that could not be written to.
     *
     * @param string $filepath Absolute or relative path to the file that could not be written
     *
     * @return self New exception instance with file path in message
     */
    public static function forPath(string $filepath): self
    {
        return new self(sprintf('Cannot write to file: "%s"', $filepath));
    }
}
