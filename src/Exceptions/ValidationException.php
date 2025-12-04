<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Exceptions;

use Exception;

use function array_map;
use function count;
use function implode;
use function sprintf;

/**
 * Exception thrown when HCL validation fails.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ValidationException extends Exception
{
    /**
     * Create a new validation exception.
     *
     * @param array<array{type: string, label: string, message: string, path: string}> $errors The validation errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'HCL validation failed',
    ) {
        parent::__construct($message);
    }

    /**
     * Create from validation errors.
     *
     * @param array<array{type: string, label: string, message: string, path: string}> $errors The validation errors
     */
    public static function fromErrors(array $errors): self
    {
        $count = count($errors);
        $messages = array_map(
            fn (array $error): string => sprintf('  - [%s] %s', $error['path'], $error['message']),
            $errors,
        );

        $message = "HCL validation failed with {$count} error(s):\n".implode("\n", $messages);

        return new self($errors, $message);
    }

    /**
     * Get the validation errors.
     *
     * @return array<array{type: string, label: string, message: string, path: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get error messages only.
     *
     * @return array<string>
     */
    public function messages(): array
    {
        return array_map(
            fn (array $error): string => $error['message'],
            $this->errors,
        );
    }
}
