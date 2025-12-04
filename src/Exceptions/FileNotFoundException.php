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
 * Exception thrown when a file is not found.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileNotFoundException extends RuntimeException implements HuckleException
{
    /**
     * Create exception for a specific file path.
     *
     * @param string $filepath Path to the file that was not found
     */
    public static function forPath(string $filepath): self
    {
        return new self(sprintf('File not found: "%s"', $filepath));
    }
}
