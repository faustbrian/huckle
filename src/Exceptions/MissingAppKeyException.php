<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Exceptions;

use RuntimeException;

/**
 * Exception thrown when APP_KEY is required but not configured.
 *
 * This exception is raised when encryption or decryption operations require
 * an application key but the APP_KEY environment variable is not set or is empty.
 * The application key is essential for secure configuration file encryption.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingAppKeyException extends RuntimeException implements HuckleException
{
    /**
     * Create an exception for missing APP_KEY configuration.
     *
     * @return self New exception instance with descriptive error message
     */
    public static function notConfigured(): self
    {
        return new self('APP_KEY is not set. Cannot use --app-key without a configured application key.');
    }
}
