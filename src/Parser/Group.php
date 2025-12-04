<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Parser;

use Illuminate\Support\Collection;

use function array_key_exists;
use function collect;
use function in_array;
use function sprintf;

/**
 * Represents a credential group (e.g., database.production).
 *
 * Groups organize related credentials within a specific environment context,
 * providing a container for multiple credentials that share common metadata
 * like tags. Groups are identified by a combination of name and environment,
 * forming paths like "database.production" or "api.staging".
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Group
{
    /**
     * Collection of credentials belonging to this group.
     *
     * @var array<string, Credential>
     */
    private array $credentials = [];

    /**
     * Create a new group instance.
     *
     * @param string        $name        Group category identifier (e.g., "database", "api", "cache")
     * @param string        $environment Environment context for this group (e.g., "production", "staging", "development")
     * @param array<string> $tags        Common tags inherited by all credentials in this group for filtering
     *                                   and categorization purposes (e.g., ["critical", "monitored"])
     */
    public function __construct(
        public readonly string $name,
        public readonly string $environment,
        public readonly array $tags = [],
    ) {}

    /**
     * Get the full path identifier for this group.
     *
     * @return string The path (e.g., "database.production")
     */
    public function path(): string
    {
        return sprintf('%s.%s', $this->name, $this->environment);
    }

    /**
     * Add a credential to this group.
     *
     * Registers a credential within this group, making it accessible by name.
     * Overwrites any existing credential with the same name.
     *
     * @param Credential $credential Credential instance to add to this group's collection
     *
     * @return self Fluent interface for method chaining
     */
    public function addCredential(Credential $credential): self
    {
        $this->credentials[$credential->name] = $credential;

        return $this;
    }

    /**
     * Get a credential by name.
     *
     * @param  string          $name The credential name
     * @return null|Credential The credential or null
     */
    public function get(string $name): ?Credential
    {
        return $this->credentials[$name] ?? null;
    }

    /**
     * Check if a credential exists.
     *
     * @param  string $name The credential name
     * @return bool   True if the credential exists
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->credentials);
    }

    /**
     * Get all credentials in this group.
     *
     * @return Collection<string, Credential> The credentials collection
     */
    public function credentials(): Collection
    {
        return collect($this->credentials);
    }

    /**
     * Get credentials filtered by tag.
     *
     * @param  string                         ...$tags Tags to filter by
     * @return Collection<string, Credential> Filtered credentials
     */
    public function tagged(string ...$tags): Collection
    {
        return $this->credentials()->filter(
            fn (Credential $cred): bool => $cred->hasAllTags($tags),
        );
    }

    /**
     * Get all exported environment variables from this group.
     *
     * Aggregates and merges export mappings from all credentials in this group.
     * Later credentials may override exports from earlier ones if they share keys.
     *
     * @return array<string, string> Merged key-value map of environment variable exports
     */
    public function export(): array
    {
        $exports = [];

        foreach ($this->credentials as $credential) {
            $exports = [...$exports, ...$credential->export()];
        }

        return $exports;
    }

    /**
     * Check if this group has a specific tag.
     *
     * @param  string $tag The tag to check
     * @return bool   True if the tag exists
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }
}
