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
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Credential
{
    /**
     * Create a new credential instance.
     *
     * @param string                $name        The credential name
     * @param string                $group       The parent group name
     * @param string                $environment The environment (e.g., production, staging)
     * @param array<string>         $tags        Associated tags
     * @param array<string, mixed>  $fields      Credential fields (host, username, etc.)
     * @param array<string, string> $exports     Environment variable mappings
     * @param array<string, string> $connections Connection command templates
     * @param null|string           $expires     Expiration date (ISO format)
     * @param null|string           $rotated     Last rotation date (ISO format)
     * @param null|string           $owner       Owner team/person
     * @param null|string           $notes       Additional notes
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
     * @param  string $name The field name
     * @return mixed  The field value
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
     * @return array<string, string> The exported variables
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
     * @param  int  $days Maximum days since last rotation
     * @return bool True if rotation is needed
     */
    public function needsRotation(int $days = 90): bool
    {
        if ($this->rotated === null) {
            return true; // Never rotated
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
     * @param  mixed $value The value to resolve
     * @return mixed The resolved value
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
