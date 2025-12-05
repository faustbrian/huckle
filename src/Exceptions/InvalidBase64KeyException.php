<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when a base64 encoded encryption key is invalid.
 *
 * Raised when attempting to decode an encryption key that contains invalid base64
 * encoding. Proper base64 encoding is required for secure key storage, transmission,
 * and configuration management in encrypted credential stores. This typically indicates
 * corrupted key data or incorrect key format in configuration files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidBase64KeyException extends InvalidArgumentException implements HuckleException
{
    /**
     * Create exception for an invalid base64 encoded encryption key.
     *
     * @return self Configured exception instance with descriptive error message
     */
    public static function invalidEncoding(): self
    {
        return new self('Invalid base64 encoded encryption key.');
    }
}
