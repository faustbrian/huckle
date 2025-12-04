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
 * @author Brian Faust <brian@cline.sh>
 */
final class Group
{
    /** @var array<string, Credential> */
    private array $credentials = [];

    /**
     * Create a new group instance.
     *
     * @param string        $name        The group name (e.g., "database")
     * @param string        $environment The environment (e.g., "production")
     * @param array<string> $tags        Tags for all credentials in this group
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
     * @param Credential $credential The credential to add
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
     * @return array<string, string> Combined exports from all credentials
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
