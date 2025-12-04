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
 * Exception thrown when a directory is not found.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DirectoryNotFoundException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a specific directory path.
     *
     * @param string $path Path to the directory that was not found
     */
    public static function forPath(string $path): self
    {
        return new self(sprintf('Directory not found: "%s"', $path));
    }
}
