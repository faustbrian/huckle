<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Parser;

use Cline\Huckle\Support\SensitiveValue;

use function array_keys;
use function is_scalar;
use function is_string;
use function preg_replace_callback;

/**
 * Represents a partition block with nested hierarchy support.
 *
 * Implements a hierarchical configuration structure supporting multiple nesting levels:
 * partition > environment > provider > country > service. Each level can define
 * fields and exports that cascade down to child levels, with more specific values
 * overriding parent values. Used for multi-region, multi-environment configurations.
 *
 * The class is named Division for historical reasons but represents any partition-type
 * block (partition, tenant, namespace, division, entity). All have identical semantics.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Division
{
    /**
     * Create a new partition instance.
     *
     * @param string                              $name         Partition identifier typically representing a business unit,
     *                                                          region, tenant, or organizational partition (e.g., "FI", "SE", "EE")
     * @param array<string, mixed>                $fields       Field definitions at the partition level that can be inherited
     *                                                          and overridden by nested environments, providers, and countries
     * @param array<string, string>               $exports      Environment variable export mappings at the partition level,
     *                                                          defining base exports that cascade to all nested levels
     * @param array<string, array<string, mixed>> $environments Nested environment configurations (e.g., production, staging)
     *                                                          containing their own fields, exports, providers, and countries
     */
    public function __construct(
        public string $name,
        public array $fields = [],
        public array $exports = [],
        public array $environments = [],
    ) {}

    /**
     * Get all exports matching the given context.
     *
     * Traverses the hierarchical structure and accumulates exports from all levels
     * that match the provided context filters. Deeper levels override parent exports
     * with the same key, allowing for context-specific configuration refinement.
     * Context keys: partition (or division for backwards compat), environment, provider, country, service.
     *
     * @param array<string, string> $context Context filter map defining which partition, environment,
     *                                       provider, country, or service to match against
     *
     * @return array<string, string> Accumulated and resolved environment variable exports
     */
    public function exportsForContext(array $context): array
    {
        // Check if this partition matches (support both 'partition' and 'division' keys)
        $partitionContext = $context['partition'] ?? $context['division'] ?? null;

        if ($partitionContext !== null && $partitionContext !== $this->name) {
            return [];
        }

        $result = [];

        // Add partition-level exports
        $result = [...$result, ...$this->resolveExports($this->exports, $this->fields)];

        // Process environments
        foreach ($this->environments as $envName => $envData) {
            // If context has environment, it must match
            if (isset($context['environment']) && $context['environment'] !== $envName) {
                continue;
            }

            // Add environment-level exports
            /** @var array<string, mixed> $envFields */
            $envFields = $envData['fields'] ?? [];

            /** @var array<string, string> $envExports */
            $envExports = $envData['exports'] ?? [];
            $result = [...$result, ...$this->resolveExports($envExports, $envFields)];

            // Process providers
            /** @var array<string, array<string, mixed>> $providers */
            $providers = $envData['providers'] ?? [];

            foreach ($providers as $providerName => $providerData) {
                // If context has provider, it must match
                if (isset($context['provider']) && $context['provider'] !== $providerName) {
                    continue;
                }

                // Add provider-level exports
                /** @var array<string, mixed> $providerFields */
                $providerFields = $providerData['fields'] ?? [];

                /** @var array<string, string> $providerExports */
                $providerExports = $providerData['exports'] ?? [];
                $result = [...$result, ...$this->resolveExports($providerExports, $providerFields)];

                // Process countries
                /** @var array<string, array<string, mixed>> $countries */
                $countries = $providerData['countries'] ?? [];

                foreach ($countries as $countryName => $countryData) {
                    // If context has country, it must match
                    if (isset($context['country']) && $context['country'] !== $countryName) {
                        continue;
                    }

                    // Add country-level exports
                    /** @var array<string, mixed> $countryFields */
                    $countryFields = $countryData['fields'] ?? [];

                    /** @var array<string, string> $countryExports */
                    $countryExports = $countryData['exports'] ?? [];
                    $result = [...$result, ...$this->resolveExports($countryExports, $countryFields)];

                    // Process services (if any)
                    /** @var array<string, array<string, mixed>> $countryServices */
                    $countryServices = $countryData['services'] ?? [];

                    foreach ($countryServices as $serviceName => $serviceData) {
                        if (isset($context['service']) && $context['service'] !== $serviceName) {
                            continue;
                        }

                        /** @var array<string, mixed> $serviceFields */
                        $serviceFields = $serviceData['fields'] ?? [];

                        /** @var array<string, string> $serviceExports */
                        $serviceExports = $serviceData['exports'] ?? [];
                        $result = [...$result, ...$this->resolveExports($serviceExports, $serviceFields)];
                    }
                }

                // Process services at provider level (if any)
                /** @var array<string, array<string, mixed>> $providerServices */
                $providerServices = $providerData['services'] ?? [];

                foreach ($providerServices as $serviceName => $serviceData) {
                    if (isset($context['service']) && $context['service'] !== $serviceName) {
                        continue;
                    }

                    /** @var array<string, mixed> $serviceFields */
                    $serviceFields = $serviceData['fields'] ?? [];

                    /** @var array<string, string> $serviceExports */
                    $serviceExports = $serviceData['exports'] ?? [];
                    $result = [...$result, ...$this->resolveExports($serviceExports, $serviceFields)];
                }
            }
        }

        return $result;
    }

    /**
     * Check if this partition matches the given context.
     *
     * @param  array<string, string> $context The context variables
     * @return bool                  True if matches
     */
    public function matches(array $context): bool
    {
        // Support both 'partition' and 'division' context keys
        $partitionContext = $context['partition'] ?? $context['division'] ?? null;

        if ($partitionContext === null) {
            return true;
        }

        return $partitionContext === $this->name;
    }

    /**
     * Get environment names.
     *
     * @return array<string> The environment names
     */
    public function environmentNames(): array
    {
        return array_keys($this->environments);
    }

    /**
     * Resolve exports with field interpolation.
     *
     * @param  array<string, string> $exports The export mappings
     * @param  array<string, mixed>  $fields  The fields for interpolation
     * @return array<string, string> The resolved exports
     */
    private function resolveExports(array $exports, array $fields): array
    {
        $result = [];

        foreach ($exports as $key => $value) {
            $resolved = $this->resolveValue($value, $fields);

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
     * Resolve interpolation in a value.
     *
     * @param  mixed                $value  The value to resolve
     * @param  array<string, mixed> $fields The fields for self.* resolution
     * @return mixed                The resolved value
     */
    private function resolveValue(mixed $value, array $fields): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return preg_replace_callback(
            '/\$\{self\.([a-zA-Z_]\w*)\}/',
            function (array $matches) use ($fields): string {
                $field = $matches[1];
                $resolved = $fields[$field] ?? '';

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
