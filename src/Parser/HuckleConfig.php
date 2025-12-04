<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Parser;

use Cline\Huckle\Support\SensitiveValue;
use Illuminate\Support\Collection;

use function array_key_exists;
use function array_map;
use function collect;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * Represents a fully parsed and resolved Huckle configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HuckleConfig
{
    /** @var array<string, Group> */
    private array $groups = [];

    /** @var array<string, Credential> */
    private array $credentials = [];

    /** @var array<string, Division> */
    private array $divisions = [];

    /** @var array<string, mixed> */
    private readonly array $defaults;

    /**
     * Create a new config instance from a parsed AST.
     *
     * @param array<string, mixed> $ast The parsed AST from the parser
     */
    public function __construct(array $ast)
    {
        /** @var array<string, mixed> $defaults */
        $defaults = $ast['defaults'] ?? [];
        $this->defaults = $defaults;
        $this->buildFromAst($ast);
    }

    /**
     * Get the default values.
     *
     * @return array<string, mixed> The defaults
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * Get all groups.
     *
     * @return Collection<string, Group> The groups collection
     */
    public function groups(): Collection
    {
        return collect($this->groups);
    }

    /**
     * Get all credentials.
     *
     * @return Collection<string, Credential> The credentials collection
     */
    public function credentials(): Collection
    {
        return collect($this->credentials);
    }

    /**
     * Get a credential by path.
     *
     * @param  string          $path The credential path (e.g., "database.production.main")
     * @return null|Credential The credential or null
     */
    public function get(string $path): ?Credential
    {
        return $this->credentials[$path] ?? null;
    }

    /**
     * Check if a credential exists.
     *
     * @param  string $path The credential path
     * @return bool   True if the credential exists
     */
    public function has(string $path): bool
    {
        return array_key_exists($path, $this->credentials);
    }

    /**
     * Get a group by path.
     *
     * @param  string     $path The group path (e.g., "database.production")
     * @return null|Group The group or null
     */
    public function group(string $path): ?Group
    {
        return $this->groups[$path] ?? null;
    }

    /**
     * Get exported environment variables for a credential.
     *
     * @param  string                $path The credential path
     * @return array<string, string> The exports
     */
    public function exports(string $path): array
    {
        $credential = $this->get($path);

        return $credential?->export() ?? [];
    }

    /**
     * Get all exported environment variables.
     *
     * @return array<string, string> Combined exports from all credentials
     */
    public function allExports(): array
    {
        $exports = [];

        foreach ($this->credentials as $credential) {
            $exports = [...$exports, ...$credential->export()];
        }

        return $exports;
    }

    /**
     * Get credentials by tag.
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
     * Get credentials in a specific environment.
     *
     * @param  string                         $environment The environment name
     * @return Collection<string, Credential> Filtered credentials
     */
    public function inEnvironment(string $environment): Collection
    {
        return $this->credentials()->filter(
            fn (Credential $cred): bool => $cred->environment === $environment,
        );
    }

    /**
     * Get credentials in a specific group.
     *
     * @param  string                         $group       The group name
     * @param  null|string                    $environment Optional environment filter
     * @return Collection<string, Credential> Filtered credentials
     */
    public function inGroup(string $group, ?string $environment = null): Collection
    {
        return $this->credentials()->filter(
            function (Credential $cred) use ($group, $environment): bool {
                if ($cred->group !== $group) {
                    return false;
                }

                if ($environment !== null && $cred->environment !== $environment) {
                    return false;
                }

                return true;
            },
        );
    }

    /**
     * Get credentials that are expiring soon.
     *
     * @param  int                            $days Number of days to consider "soon"
     * @return Collection<string, Credential> Expiring credentials
     */
    public function expiring(int $days = 30): Collection
    {
        return $this->credentials()->filter(
            fn (Credential $cred): bool => $cred->isExpiring($days),
        );
    }

    /**
     * Get credentials that are expired.
     *
     * @return Collection<string, Credential> Expired credentials
     */
    public function expired(): Collection
    {
        return $this->credentials()->filter(
            fn (Credential $cred): bool => $cred->isExpired(),
        );
    }

    /**
     * Get credentials that need rotation.
     *
     * @param  int                            $days Maximum days since last rotation
     * @return Collection<string, Credential> Credentials needing rotation
     */
    public function needsRotation(int $days = 90): Collection
    {
        return $this->credentials()->filter(
            fn (Credential $cred): bool => $cred->needsRotation($days),
        );
    }

    /**
     * Get all divisions.
     *
     * @return Collection<string, Division> The divisions collection
     */
    public function divisions(): Collection
    {
        return collect($this->divisions);
    }

    /**
     * Get a division by name.
     *
     * @param  string        $name The division name
     * @return null|Division The division or null
     */
    public function division(string $name): ?Division
    {
        return $this->divisions[$name] ?? null;
    }

    /**
     * Get divisions that match the given context.
     *
     * @param  array<string, string>        $context The context variables
     * @return Collection<string, Division> Matching divisions
     */
    public function matchingDivisions(array $context): Collection
    {
        return $this->divisions()->filter(
            fn (Division $division): bool => $division->matches($context),
        );
    }

    /**
     * Get all exports from divisions matching the given context.
     *
     * Accumulates exports from all matching levels in the hierarchy.
     * More specific (deeper) exports override parent exports with same key.
     *
     * @param  array<string, string> $context The context variables
     * @return array<string, string> The combined exports
     */
    public function exportsForContext(array $context): array
    {
        $exports = [];

        foreach ($this->divisions as $division) {
            $exports = [...$exports, ...$division->exportsForContext($context)];
        }

        return $exports;
    }

    /**
     * Build the config from the parsed AST.
     *
     * @param array<string, mixed> $ast The AST
     */
    private function buildFromAst(array $ast): void
    {
        /** @var array<int, array<string, mixed>> $groups */
        $groups = $ast['groups'] ?? [];

        foreach ($groups as $groupData) {
            $this->processGroup($groupData);
        }

        /** @var array<int, array<string, mixed>> $divisions */
        $divisions = $ast['divisions'] ?? [];

        foreach ($divisions as $divisionData) {
            $this->processDivision($divisionData);
        }
    }

    /**
     * Process a group from the AST.
     *
     * @param array<string, mixed> $groupData The group data
     */
    private function processGroup(array $groupData): void
    {
        /** @var array<int, string> $labels */
        $labels = $groupData['labels'] ?? [];

        /** @var string $groupName */
        $groupName = $labels[0] ?? 'default';

        /** @var string $environment */
        $environment = $labels[1] ?? 'default';

        /** @var array<string, mixed> $body */
        $body = $groupData['body'] ?? [];

        // Extract group-level tags
        $tags = $this->extractTags($body['tags'] ?? null);

        $group = new Group($groupName, $environment, $tags);
        $groupPath = $group->path();

        $this->groups[$groupPath] = $group;

        // Process credentials within the group
        /** @var array<int, array<string, mixed>> $credentialBlocks */
        $credentialBlocks = $body['credential'] ?? [];

        foreach ($credentialBlocks as $credentialData) {
            $credential = $this->processCredential($credentialData, $group);
            $group->addCredential($credential);
            $this->credentials[$credential->path()] = $credential;
        }
    }

    /**
     * Process a credential from the AST.
     *
     * @param  array<string, mixed> $credentialData The credential data
     * @param  Group                $group          The parent group
     * @return Credential           The built credential
     */
    private function processCredential(array $credentialData, Group $group): Credential
    {
        /** @var array<int, string> $labels */
        $labels = $credentialData['labels'] ?? [];

        /** @var string $name */
        $name = $labels[0] ?? 'unnamed';

        /** @var array<string, mixed> $body */
        $body = $credentialData['body'] ?? [];

        // Extract special fields
        $tags = [...$group->tags, ...$this->extractTags($body['tags'] ?? null)];

        /** @var array<int, array<string, mixed>> $exportBlocks */
        $exportBlocks = $body['export'] ?? [];
        $exports = $this->extractExports($exportBlocks);

        /** @var array<int, array<string, mixed>> $connectBlocks */
        $connectBlocks = $body['connect'] ?? [];
        $connections = $this->extractConnections($connectBlocks);

        // Remove processed fields from body
        /** @var array<string, mixed> $fields */
        $fields = $body;
        unset($fields['tags'], $fields['export'], $fields['connect'], $fields['expires'], $fields['rotated'], $fields['owner'], $fields['notes']);

        // Process remaining fields (resolve values)
        /** @var array<string, mixed> $processedFields */
        $processedFields = [];

        foreach ($fields as $key => $value) {
            $processedFields[$key] = $this->resolveAstValue($value);
        }

        return new Credential(
            name: $name,
            group: $group->name,
            environment: $group->environment,
            tags: $tags,
            fields: $processedFields,
            exports: $exports,
            connections: $connections,
            expires: $this->extractScalarValue($body['expires'] ?? null),
            rotated: $this->extractScalarValue($body['rotated'] ?? null),
            owner: $this->extractScalarValue($body['owner'] ?? null),
            notes: $this->extractScalarValue($body['notes'] ?? null),
        );
    }

    /**
     * Process a division from the AST.
     *
     * Handles nested hierarchy: division > environment > provider > country > service
     *
     * @param array<string, mixed> $divisionData The division data
     */
    private function processDivision(array $divisionData): void
    {
        /** @var array<int, string> $labels */
        $labels = $divisionData['labels'] ?? [];

        /** @var string $name */
        $name = $labels[0] ?? 'default';

        /** @var array<string, mixed> $body */
        $body = $divisionData['body'] ?? [];

        // Extract division-level fields and exports
        $divisionFields = $this->extractFieldsFromBody($body);

        /** @var array<int, array<string, mixed>> $exportBlocks */
        $exportBlocks = $body['export'] ?? [];
        $divisionExports = $this->extractExports($exportBlocks);

        // Process environments
        /** @var array<string, array<string, mixed>> $environments */
        $environments = [];

        /** @var array<int, array<string, mixed>> $envBlocks */
        $envBlocks = $body['environment'] ?? [];

        foreach ($envBlocks as $envBlock) {
            /** @var array<int, string> $envLabels */
            $envLabels = $envBlock['labels'] ?? [];

            /** @var string $envName */
            $envName = $envLabels[0] ?? 'default';

            /** @var array<string, mixed> $envBody */
            $envBody = $envBlock['body'] ?? [];

            $envFields = $this->extractFieldsFromBody($envBody);

            /** @var array<int, array<string, mixed>> $envExportBlocks */
            $envExportBlocks = $envBody['export'] ?? [];
            $envExports = $this->extractExports($envExportBlocks);

            // Process providers within environment
            /** @var array<string, array<string, mixed>> $providers */
            $providers = [];

            /** @var array<int, array<string, mixed>> $providerBlocks */
            $providerBlocks = $envBody['provider'] ?? [];

            foreach ($providerBlocks as $providerBlock) {
                /** @var array<int, string> $providerLabels */
                $providerLabels = $providerBlock['labels'] ?? [];

                /** @var string $providerName */
                $providerName = $providerLabels[0] ?? 'default';

                /** @var array<string, mixed> $providerBody */
                $providerBody = $providerBlock['body'] ?? [];

                $providerFields = $this->extractFieldsFromBody($providerBody);

                /** @var array<int, array<string, mixed>> $providerExportBlocks */
                $providerExportBlocks = $providerBody['export'] ?? [];
                $providerExports = $this->extractExports($providerExportBlocks);

                // Process countries within provider
                /** @var array<string, array<string, mixed>> $countries */
                $countries = [];

                /** @var array<int, array<string, mixed>> $countryBlocks */
                $countryBlocks = $providerBody['country'] ?? [];

                foreach ($countryBlocks as $countryBlock) {
                    /** @var array<int, string> $countryLabels */
                    $countryLabels = $countryBlock['labels'] ?? [];

                    /** @var string $countryName */
                    $countryName = $countryLabels[0] ?? 'default';

                    /** @var array<string, mixed> $countryBody */
                    $countryBody = $countryBlock['body'] ?? [];

                    $countryFields = $this->extractFieldsFromBody($countryBody);

                    /** @var array<int, array<string, mixed>> $countryExportBlocks */
                    $countryExportBlocks = $countryBody['export'] ?? [];
                    $countryExports = $this->extractExports($countryExportBlocks);

                    // Process services within country
                    $countryServices = $this->extractServices($countryBody);

                    $countries[$countryName] = [
                        'fields' => $countryFields,
                        'exports' => $countryExports,
                        'services' => $countryServices,
                    ];
                }

                // Process services at provider level
                $providerServices = $this->extractServices($providerBody);

                $providers[$providerName] = [
                    'fields' => $providerFields,
                    'exports' => $providerExports,
                    'countries' => $countries,
                    'services' => $providerServices,
                ];
            }

            $environments[$envName] = [
                'fields' => $envFields,
                'exports' => $envExports,
                'providers' => $providers,
            ];
        }

        $division = new Division($name, $divisionFields, $divisionExports, $environments);
        $this->divisions[$name] = $division;
    }

    /**
     * Extract fields from a block body (excluding nested blocks and exports).
     *
     * @param  array<string, mixed> $body The block body
     * @return array<string, mixed> The extracted fields
     */
    private function extractFieldsFromBody(array $body): array
    {
        $excludeKeys = ['export', 'environment', 'provider', 'country', 'service', 'carrier', 'if'];
        $fields = [];

        foreach ($body as $key => $value) {
            if (in_array($key, $excludeKeys, true)) {
                continue;
            }

            // Skip if it's a nested block (array of blocks with 'labels' and 'body')
            if (is_array($value)) {
                /** @var array<int|string, mixed> $arrayValue */
                $arrayValue = $value;

                if (isset($arrayValue[0]) && is_array($arrayValue[0]) && isset($arrayValue[0]['labels'])) {
                    continue;
                }
            }

            $fields[$key] = $this->resolveAstValue($value);
        }

        return $fields;
    }

    /**
     * Extract services from a block body.
     *
     * @param  array<string, mixed>                                                               $body The block body
     * @return array<string, array{fields: array<string, mixed>, exports: array<string, string>}> The extracted services
     */
    private function extractServices(array $body): array
    {
        /** @var array<string, array{fields: array<string, mixed>, exports: array<string, string>}> $services */
        $services = [];

        /** @var array<int, array<string, mixed>> $serviceBlocks */
        $serviceBlocks = $body['service'] ?? [];

        foreach ($serviceBlocks as $serviceBlock) {
            /** @var array<int, string> $serviceLabels */
            $serviceLabels = $serviceBlock['labels'] ?? [];

            /** @var string $serviceName */
            $serviceName = $serviceLabels[0] ?? 'default';

            /** @var array<string, mixed> $serviceBody */
            $serviceBody = $serviceBlock['body'] ?? [];

            /** @var array<int, array<string, mixed>> $serviceExportBlocks */
            $serviceExportBlocks = $serviceBody['export'] ?? [];

            $services[$serviceName] = [
                'fields' => $this->extractFieldsFromBody($serviceBody),
                'exports' => $this->extractExports($serviceExportBlocks),
            ];
        }

        return $services;
    }

    /**
     * Extract tags from an AST value.
     *
     * @param  mixed         $value The AST value
     * @return array<string> The tags
     */
    private function extractTags(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value) && ($value['_type'] ?? null) === 'array') {
            $tags = [];

            /** @var array<int, mixed> $items */
            $items = $value['value'] ?? [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (!isset($item['value'])) {
                    continue;
                }

                /** @var scalar $itemValue */
                $itemValue = $item['value'];
                $tags[] = (string) $itemValue;
            }

            return $tags;
        }

        return [];
    }

    /**
     * Extract exports from AST.
     *
     * @param  array<int, array<string, mixed>> $exportBlocks The export blocks
     * @return array<string, string>            The exports
     */
    private function extractExports(array $exportBlocks): array
    {
        $exports = [];

        foreach ($exportBlocks as $block) {
            /** @var array<string, mixed> $body */
            $body = $block['body'] ?? [];

            foreach ($body as $key => $value) {
                $resolved = $this->resolveAstValue($value);

                if (is_string($resolved)) {
                    $exports[$key] = $resolved;
                } elseif (is_scalar($resolved)) {
                    $exports[$key] = (string) $resolved;
                } else {
                    $exports[$key] = '';
                }
            }
        }

        return $exports;
    }

    /**
     * Extract connections from AST.
     *
     * @param  array<int, array<string, mixed>> $connectBlocks The connect blocks
     * @return array<string, string>            The connections
     */
    private function extractConnections(array $connectBlocks): array
    {
        $connections = [];

        foreach ($connectBlocks as $block) {
            /** @var array<int, string> $labels */
            $labels = $block['labels'] ?? [];
            $name = $labels[0] ?? 'default';

            /** @var array<string, mixed> $body */
            $body = $block['body'] ?? [];

            if (!isset($body['command'])) {
                continue;
            }

            $resolved = $this->resolveAstValue($body['command']);

            if (is_string($resolved)) {
                $connections[$name] = $resolved;
            } elseif (is_scalar($resolved)) {
                $connections[$name] = (string) $resolved;
            } else {
                $connections[$name] = '';
            }
        }

        return $connections;
    }

    /**
     * Extract a scalar value from an AST node.
     *
     * @param  mixed       $value The AST value
     * @return null|string The scalar value
     */
    private function extractScalarValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && isset($value['value'])) {
            /** @var scalar $scalarValue */
            $scalarValue = $value['value'];

            return (string) $scalarValue;
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Resolve an AST value to a PHP value.
     *
     * @param  mixed $value The AST value
     * @return mixed The resolved value
     */
    private function resolveAstValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $type = $value['_type'] ?? null;

        /** @var array<mixed> $arrayValue */
        $arrayValue = $value['value'] ?? [];

        /** @var array<string, mixed> $typedValue */
        $typedValue = $value;

        return match ($type) {
            'string' => $value['value'],
            'number' => $value['value'],
            'bool' => $value['value'],
            'null' => null,
            'array' => array_map($this->resolveAstValue(...), $arrayValue),
            'object' => array_map($this->resolveAstValue(...), $arrayValue),
            'function' => $this->resolveFunction($typedValue),
            'reference' => $this->resolveReference($typedValue),
            'identifier' => $value['value'],
            default => $value,
        };
    }

    /**
     * Resolve a function call.
     *
     * @param  array<string, mixed> $func The function data
     * @return mixed                The resolved value
     */
    private function resolveFunction(array $func): mixed
    {
        /** @var string $name */
        $name = $func['name'];

        /** @var array<int, mixed> $args */
        $args = $func['args'] ?? [];

        if ($name === 'sensitive' && count($args) > 0) {
            $value = $this->resolveAstValue($args[0]);
            $stringValue = is_scalar($value) ? (string) $value : '';

            return new SensitiveValue($stringValue);
        }

        // Unknown function - return as-is
        return $func;
    }

    /**
     * Resolve a reference (e.g., self.host).
     *
     * @param  array<string, mixed> $ref The reference data
     * @return string               The reference string (for later resolution)
     */
    private function resolveReference(array $ref): string
    {
        /** @var array<int, string> $parts */
        $parts = $ref['parts'] ?? [];

        // Return as interpolation string for later resolution
        return '${'.implode('.', $parts).'}';
    }
}
