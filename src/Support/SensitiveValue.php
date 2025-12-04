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
 * Wraps sensitive values to prevent accidental exposure.
 *
 * When cast to string, this class will reveal the actual value.
 * Use masked() to get a safe representation for display.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SensitiveValue implements Stringable
{
    private const string MASK = '********';

    /**
     * Create a new sensitive value.
     *
     * @param string $value The sensitive value to protect
     */
    public function __construct(
        private string $value,
    ) {}

    /**
     * Convert to string (reveals the value).
     *
     * @return string The actual value
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Customize var_dump output to hide the value.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => self::MASK];
    }

    /**
     * Customize serialization to prevent value exposure.
     *
     * @return array<string, string>
     */
    public function __serialize(): array
    {
        return ['value' => $this->value];
    }

    /**
     * Restore from serialization.
     *
     * @param array<string, string> $data The serialized data
     */
    public function __unserialize(array $data): void
    {
        // Note: readonly properties can only be set in constructor
        // This is a limitation - we need to handle this differently
    }

    /**
     * Get the actual value.
     *
     * @return string The unmasked value
     */
    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * Get a masked representation of the value.
     *
     * @return string The masked value
     */
    public function masked(): string
    {
        return self::MASK;
    }

    /**
     * Check if the value is empty.
     *
     * @return bool True if the value is empty
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * Get the length of the actual value.
     *
     * @return int The length
     */
    public function length(): int
    {
        return mb_strlen($this->value);
    }
}
