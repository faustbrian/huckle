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
 * Exception thrown when configuration file encryption fails.
 *
 * Occurs when the encryption process encounters errors such as missing
 * encryption keys, invalid cipher configurations, or filesystem write failures.
 * May indicate cryptographic library issues or insufficient system resources
 * for encryption operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class EncryptionFailedException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a specific configuration file path.
     *
     * @param string $filepath Absolute or relative path to the configuration file that failed encryption
     * @param string $reason   Detailed explanation of why the encryption operation failed
     *
     * @return self Configured exception instance with formatted error message
     */
    public static function forPath(string $filepath, string $reason): self
    {
        return new self(sprintf(
            'Failed to encrypt configuration file "%s": %s',
            $filepath,
            $reason,
        ));
    }
}
