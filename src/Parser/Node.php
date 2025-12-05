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
use function implode;
use function in_array;
use function is_scalar;
use function is_string;
use function now;
use function preg_replace_callback;

/**
 * Represents a configuration node in the hierarchical structure.
 *
 * A Node is the unified representation of any block in the HCL configuration,
 * whether it's a partition, tenant, environment, provider, country, or service.
 * All block types share the same structure and behavior, with semantic meaning
 * derived from their position in the hierarchy and their type label.
 *
 * Nodes can have:
 * - Fields: key-value configuration data
 * - Exports: environment variable mappings
 * - Connections: connection command templates
 * - Metadata: expires, rotated, owner, notes, tags
 * - Children: nested nodes at deeper hierarchy levels
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Node
{
    /**
     * Create a new node instance.
     *
     * @param string                $type        Block type (partition, tenant, environment, provider, country, service, etc.)
     * @param string                $name        Block label/identifier (e.g., "FI", "production", "posti")
     * @param array<string>         $path        Full path from root (e.g., ["FI", "production", "posti"])
     * @param array<string, mixed>  $fields      Field values for this node
     * @param array<string, string> $exports     Environment variable export mappings
     * @param array<string, string> $connections Connection command templates with ${self.field} interpolation
     * @param array<string>         $tags        Metadata tags for filtering
     * @param null|string           $expires     ISO 8601 expiration date
     * @param null|string           $rotated     ISO 8601 last rotation date
     * @param null|string           $owner       Responsible party
     * @param null|string           $notes       Additional documentation
     * @param array<string, self>   $children    Nested child nodes
     */
    public function __construct(
        public string $type,
        public string $name,
        public array $path,
        public array $fields = [],
        public array $exports = [],
        public array $connections = [],
        public array $tags = [],
        public ?string $expires = null,
        public ?string $rotated = null,
        public ?string $owner = null,
        public ?string $notes = null,
        public array $children = [],
    ) {}

    /**
     * Magic getter for field access.
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
     * Get the full path as a dot-separated string.
     *
     * @return string The path (e.g., "FI.production.posti")
     */
    public function pathString(): string
    {
        return implode('.', $this->path);
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
     * Check if the node is expired.
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
     * Check if the node is expiring soon.
     *
     * @param  int  $days Number of days to consider "soon"
     * @return bool True if expiring within the given days
     */
    public function isExpiring(int $days = 30): bool
    {
        if ($this->expires === null) {
            return false;
        }

        $now = Date::now();
        $expiryDate = Date::parse($this->expires);

        // Node is expiring if: expiry is in future AND less than $days away
        // diffInDays returns negative when comparing future to now, so use absolute=true
        return $expiryDate->isAfter($now) && $expiryDate->diffInDays($now, absolute: true) <= $days;
    }

    /**
     * Check if the node needs rotation.
     *
     * @param int $days Maximum days since last rotation (default: 90)
     *
     * @return bool True if rotation is needed
     */
    public function needsRotation(int $days = 90): bool
    {
        if ($this->rotated === null) {
            return true;
        }

        return Date::parse($this->rotated)->diffInDays(now()) >= $days;
    }

    /**
     * Check if the node has a specific tag.
     *
     * @param  string $tag The tag to check
     * @return bool   True if the tag exists
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Check if the node has all of the given tags.
     *
     * @param  array<string> $tags The tags to check
     * @return bool          True if all tags exist
     */
    public function hasAllTags(array $tags): bool
    {
        return array_all($tags, fn (string $tag): bool => $this->hasTag($tag));
    }

    /**
     * Check if the node has any of the given tags.
     *
     * @param  array<string> $tags The tags to check
     * @return bool          True if any tag exists
     */
    public function hasAnyTag(array $tags): bool
    {
        return array_any($tags, fn (string $tag): bool => $this->hasTag($tag));
    }

    /**
     * Check if this node matches the given context.
     *
     * A node matches if all context keys that are present match the node's
     * path or type. Context keys can be: partition, tenant, environment,
     * provider, country, service, or any custom type name.
     *
     * @param  array<string, string> $context The context to match against
     * @return bool                  True if matches
     */
    public function matches(array $context): bool
    {
        // Build a map of type -> name from path for matching
        // This requires knowing the types at each level, which we track in the path
        // For now, we match against the path elements directly

        foreach ($context as $key => $value) {
            // Match by position in path based on known hierarchy
            $position = match ($key) {
                'partition', 'tenant', 'namespace', 'division', 'entity' => 0,
                'environment' => 1,
                'provider' => 2,
                'country' => 3,
                'service' => 4,
                default => null,
            };

            if ($position === null) {
                continue;
            }

            // If path doesn't have this position, node doesn't match
            if (!isset($this->path[$position])) {
                return false;
            }

            if ($this->path[$position] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a child node by name.
     *
     * @param  string    $name The child name
     * @return null|self The child or null
     */
    public function child(string $name): ?self
    {
        return $this->children[$name] ?? null;
    }

    /**
     * Check if a child exists.
     *
     * @param  string $name The child name
     * @return bool   True if exists
     */
    public function hasChild(string $name): bool
    {
        return isset($this->children[$name]);
    }

    /**
     * Resolve interpolation in a value.
     *
     * @param  mixed $value Value potentially containing field references
     * @return mixed Resolved value with interpolations replaced
     */
    private function resolveValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

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
