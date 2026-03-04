<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Support;

use Stringable;

use function mb_strlen;

/**
 * Wraps sensitive values to prevent accidental exposure in logs and debugging output.
 *
 * Provides a secure wrapper for sensitive data like passwords, API keys, and tokens.
 * The value is protected from var_dump and print_r output but can be explicitly
 * revealed when needed. String casting reveals the actual value for intentional use.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SensitiveValue implements Stringable
{
    /**
     * Mask string used for hiding sensitive values in debug output.
     */
    private const string MASK = '********';

    /**
     * Create a new sensitive value wrapper.
     *
     * @param string $value The sensitive value to protect from accidental exposure.
     *                      Examples include passwords, API keys, tokens, and other
     *                      credentials that should not appear in logs or debug output.
     */
    public function __construct(
        private string $value,
    ) {}

    /**
     * Convert to string, revealing the actual sensitive value.
     *
     * Use this intentionally when the actual value is needed for operations
     * like authentication or API calls. The value is exposed only when explicitly
     * cast to string, not during debugging or logging.
     *
     * @return string The actual unmasked sensitive value
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Customize var_dump output to prevent sensitive value exposure.
     *
     * Replaces the actual value with a masked placeholder when debugging
     * to prevent accidental credential leaks in debug output or logs.
     *
     * @return array<string, string> Debug representation with masked value
     */
    public function __debugInfo(): array
    {
        return ['value' => self::MASK];
    }

    /**
     * Serialize the sensitive value for storage or transmission.
     *
     * Preserves the actual value during serialization to allow proper
     * reconstruction. Ensure serialized data is stored securely with
     * appropriate encryption.
     *
     * @return array<string, string> Serialized data containing the actual value
     */
    public function __serialize(): array
    {
        return ['value' => $this->value];
    }

    /**
     * Restore sensitive value from serialized data.
     *
     * Note: This method has a limitation with readonly properties in PHP 8.1+
     * as they can only be initialized in the constructor. Consider using
     * alternative deserialization approaches if needed.
     *
     * @param array<string, string> $data The serialized data to restore from
     */
    public function __unserialize(array $data): void
    {
        // Readonly properties cannot be modified outside constructor
        // This method exists for Serializable interface compatibility
        // but cannot set the value due to readonly constraint
    }

    /**
     * Explicitly reveal the actual sensitive value.
     *
     * Use this method when you need intentional access to the raw value
     * for authentication, API calls, or other operations requiring the
     * actual credential.
     *
     * @return string The unmasked sensitive value
     */
    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * Get a safe masked representation for display or logging.
     *
     * Returns a placeholder string that can be safely shown in logs,
     * error messages, or user interfaces without exposing the actual
     * sensitive value.
     *
     * @return string The masked placeholder string
     */
    public function masked(): string
    {
        return self::MASK;
    }

    /**
     * Check if the sensitive value is an empty string.
     *
     * Useful for validation without exposing the actual value content.
     * Returns true for empty strings, false otherwise.
     *
     * @return bool True if the underlying value is an empty string
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * Get the character length of the sensitive value.
     *
     * Returns the multibyte-safe character count without exposing the
     * actual value. Useful for validation (e.g., password length requirements)
     * without revealing the credential.
     *
     * @return int The number of characters in the value
     */
    public function length(): int
    {
        return mb_strlen($this->value);
    }
}
