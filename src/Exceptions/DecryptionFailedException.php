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
 * Exception thrown when encrypted configuration file decryption fails.
 *
 * Occurs when the decryption process encounters errors such as invalid
 * encryption keys, corrupted encrypted data, or unsupported cipher methods.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DecryptionFailedException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a specific configuration file path.
     *
     * @param string $filepath Absolute or relative path to the configuration file that failed decryption
     * @param string $reason   Detailed explanation of why the decryption operation failed
     */
    public static function forPath(string $filepath, string $reason): self
    {
        return new self(sprintf(
            'Failed to decrypt configuration file "%s": %s',
            $filepath,
            $reason,
        ));
    }
}
