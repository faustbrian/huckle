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
 * This exception encapsulates validation errors from HCL configuration parsing,
 * providing structured error information including error paths and messages for
 * debugging and user feedback.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ValidationException extends Exception implements HuckleException
{
    /**
     * Create a new validation exception.
     *
     * @param array<array{type: string, label: string, message: string, path: string}> $errors  Structured validation error data containing error types,
     *                                                                                          labels, descriptive messages, and JSON pointer paths to the
     *                                                                                          specific locations in the HCL document that failed validation
     * @param string                                                                   $message Human-readable exception message summarizing the validation failure
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'HCL validation failed',
    ) {
        parent::__construct($message);
    }

    /**
     * Create from validation errors with formatted message.
     *
     * This static factory method creates a new exception instance with a detailed
     * multi-line message listing all validation errors with their paths and messages.
     *
     * @param array<array{type: string, label: string, message: string, path: string}> $errors Structured validation error data from the HCL parser
     *
     * @return self New exception instance with formatted error message
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
     * Get all validation errors.
     *
     * Returns the complete structured error data including types, labels,
     * messages, and paths for programmatic error handling.
     *
     * @return array<array{type: string, label: string, message: string, path: string}> Complete structured error data
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get error messages only.
     *
     * Extracts just the human-readable error messages from the structured
     * error data, useful for simple error displays without path information.
     *
     * @return array<string> Array of error message strings without paths or metadata
     */
    public function messages(): array
    {
        return array_map(
            fn (array $error): string => $error['message'],
            $this->errors,
        );
    }
}
