<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Exceptions;

use Throwable;

/**
 * Marker interface for all Huckle package exceptions.
 *
 * Enables unified exception handling by providing a common type for all exceptions
 * thrown by the Huckle credential management system. Allows consumers to catch all
 * Huckle-specific errors with a single catch block while maintaining the ability to
 * catch specific exception types when more granular error handling is needed.
 *
 * ```php
 * try {
 *     $node = $huckle->get('path.to.node');
 * } catch (HuckleException $e) {
 *     // Handle any Huckle-related error
 *     logger()->error('Huckle error: ' . $e->getMessage());
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface HuckleException extends Throwable {}
