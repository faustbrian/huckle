<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Parser;

use Cline\Huckle\Support\SensitiveValue;
use Illuminate\Support\Facades\Date;

use function array_all;
use function array_any;
use function array_key_exists;
use function array_keys;
use function in_array;
use function is_scalar;
use function is_string;
use function now;
use function preg_replace_callback;
use function sprintf;

/**
 * Represents a parsed credential entry.
 *
 * Encapsulates all data for a single credential including its fields, metadata,
 * export mappings, connection commands, and lifecycle tracking information.
 * Provides methods for field access, environment exports, and credential
 * lifecycle management (expiration, rotation).
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Credential
{
    /**
     * Create a new credential instance.
     *
     * @param string                $name        Unique credential identifier within its group (e.g., "main", "replica")
     * @param string                $group       Parent group name that categorizes this credential (e.g., "database", "api")
     * @param string                $environment Environment context where this credential applies (e.g., "production", "staging")
     * @param array<string>         $tags        Metadata tags for filtering and categorization (e.g., ["critical", "pci"])
     * @param array<string, mixed>  $fields      Credential field data containing sensitive values like host, username,
     *                                           password, port, etc. Field values can be strings, numbers, or SensitiveValue objects
     * @param array<string, string> $exports     Environment variable export mappings defining which fields should be
     *                                           exported to environment variables and their target variable names
     * @param array<string, string> $connections Connection command templates for different connection types (e.g., "cli", "ssh")
     *                                           with field interpolation support using ${self.field} syntax
     * @param null|string           $expires     ISO 8601 formatted expiration date when this credential becomes invalid
     * @param null|string           $rotated     ISO 8601 formatted date of last credential rotation for security compliance tracking
     * @param null|string           $owner       Team or individual responsible for maintaining this credential
     * @param null|string           $notes       Additional documentation or context about credential usage and requirements
     */
    public function __construct(
        public string $name,
        public string $group,
        public string $environment,
        public array $tags = [],
        public array $fields = [],
        public array $exports = [],
        public array $connections = [],
        public ?string $expires = null,
        public ?string $rotated = null,
        public ?string $owner = null,
        public ?string $notes = null,
    ) {}

    /**
     * Magic getter for field access.
     *
     * Provides property-style access to credential fields using object notation.
     * Example: $credential->host returns the 'host' field value.
     *
     * @param string $name Field name to retrieve
     *
     * @return mixed Field value or null if field doesn't exist
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Magic isset check.
     *
     * @param  string $name The field name
     * @return bool   True if the field exists
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Get the full path identifier for this credential.
     *
     * @return string The path (e.g., "database.production.main")
     */
    public function path(): string
    {
        return sprintf('%s.%s.%s', $this->group, $this->environment, $this->name);
    }

    /**
     * Get a field value.
     *
     * @param  string $name The field name
     * @return mixed  The field value or null
     */
    public function get(string $name): mixed
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * Check if a field exists.
     *
     * @param  string $name The field name
     * @return bool   True if the field exists
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->fields);
    }

    /**
     * Get all field names.
     *
     * @return array<string> The field names
     */
    public function fieldNames(): array
    {
        return array_keys($this->fields);
    }

    /**
     * Get exported environment variables with resolved values.
     *
     * Resolves export mappings by interpolating field references (${self.field})
     * and converting SensitiveValue objects to plain strings. Returns a flat
     * key-value map ready for injection into environment variables.
     *
     * @return array<string, string> Key-value map of environment variable names to resolved values
     */
    public function export(): array
    {
        $result = [];

        foreach ($this->exports as $key => $value) {
            $resolved = $this->resolveValue($value);

            if ($resolved instanceof SensitiveValue) {
                $result[$key] = $resolved->reveal();
            } elseif (is_scalar($resolved)) {
                $result[$key] = (string) $resolved;
            } else {
                $result[$key] = '';
            }
        }

        return $result;
    }

    /**
     * Get a connection command with resolved values.
     *
     * @param  string      $name The connection name
     * @return null|string The resolved command or null
     */
    public function connection(string $name): ?string
    {
        if (!isset($this->connections[$name])) {
            return null;
        }

        $resolved = $this->resolveValue($this->connections[$name]);

        if (is_scalar($resolved)) {
            return (string) $resolved;
        }

        return '';
    }

    /**
     * Get all connection names.
     *
     * @return array<string> The connection names
     */
    public function connectionNames(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Check if the credential is expired.
     *
     * @return bool True if expired
     */
    public function isExpired(): bool
    {
        if ($this->expires === null) {
            return false;
        }

        return Date::parse($this->expires)->isPast();
    }

    /**
     * Check if the credential is expiring soon.
     *
     * @param  int  $days Number of days to consider "soon"
     * @return bool True if expiring within the given days
     */
    public function isExpiring(int $days = 30): bool
    {
        if ($this->expires === null) {
            return false;
        }

        $expiryDate = Date::parse($this->expires);

        return $expiryDate->isFuture() && $expiryDate->diffInDays(now()) <= $days;
    }

    /**
     * Check if the credential needs rotation.
     *
     * Determines if the credential has exceeded the maximum rotation age
     * based on its last rotation date. Credentials that have never been
     * rotated always return true, promoting security best practices.
     *
     * @param int $days Maximum days since last rotation (default: 90)
     *
     * @return bool True if rotation is needed (never rotated or exceeded max age)
     */
    public function needsRotation(int $days = 90): bool
    {
        if ($this->rotated === null) {
            return true; // Never rotated - requires initial rotation
        }

        return Date::parse($this->rotated)->diffInDays(now()) >= $days;
    }

    /**
     * Check if the credential has a specific tag.
     *
     * @param  string $tag The tag to check
     * @return bool   True if the tag exists
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Check if the credential has all of the given tags.
     *
     * @param  array<string> $tags The tags to check
     * @return bool          True if all tags exist
     */
    public function hasAllTags(array $tags): bool
    {
        return array_all($tags, fn (string $tag): bool => $this->hasTag($tag));
    }

    /**
     * Check if the credential has any of the given tags.
     *
     * @param  array<string> $tags The tags to check
     * @return bool          True if any tag exists
     */
    public function hasAnyTag(array $tags): bool
    {
        return array_any($tags, fn (string $tag): bool => $this->hasTag($tag));
    }

    /**
     * Resolve interpolation in a value.
     *
     * Processes field references using ${self.field} syntax, replacing them
     * with actual field values. Handles SensitiveValue objects by revealing
     * their contents during interpolation. Non-string values pass through unchanged.
     *
     * @param mixed $value Value potentially containing field references
     *
     * @return mixed Resolved value with interpolations replaced
     */
    private function resolveValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Replace ${self.field} references
        return preg_replace_callback(
            '/\$\{self\.([a-zA-Z_]\w*)\}/',
            function (array $matches): string {
                $field = $matches[1];
                $resolved = $this->get($field);

                if ($resolved instanceof SensitiveValue) {
                    return $resolved->reveal();
                }

                if (is_scalar($resolved)) {
                    return (string) $resolved;
                }

                return '';
            },
            $value,
        );
    }
}
