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
use function array_unique;
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
 * All configuration blocks (partitions, tenants, environments, providers, etc.)
 * are unified into Node objects. Nodes form a flat, queryable collection that
 * can be filtered by context (partition, environment, provider, etc.).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HuckleConfig
{
    /**
     * Flat collection of all nodes indexed by path.
     *
     * @var array<string, Node>
     */
    private array $nodes = [];

    /**
     * Partition-level nodes for context-based export resolution.
     *
     * @var array<string, Node>
     */
    private array $partitions = [];

    /**
     * Fallback nodes for default exports.
     *
     * @var array<string, Node>
     */
    private array $fallbacks = [];

    /**
     * Default configuration values from the AST.
     *
     * @var array<string, mixed>
     */
    private readonly array $defaults;

    /**
     * Create a new config instance from a parsed AST.
     *
     * Processes the AST to build a flat, queryable node collection with support
     * for partitions, fallbacks, groups, and environment-based hierarchies.
     *
     * @param array<string, mixed> $ast Parsed abstract syntax tree from the HCL parser
     */
    public function __construct(array $ast)
    {
        /** @var array<string, mixed> $defaults */
        $defaults = $ast['defaults'] ?? [];
        $this->defaults = $defaults;
        $this->buildFromAst($ast);
    }

    /**
     * Get the default configuration values.
     *
     * Returns base configuration values that apply to all nodes unless overridden
     * at deeper hierarchy levels.
     *
     * @return array<string, mixed> Default configuration key-value pairs
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * Get all configuration nodes.
     *
     * Returns the complete flat collection of all nodes indexed by their
     * dot-separated path strings (e.g., "FI.production.posti").
     *
     * @return Collection<string, Node> All nodes indexed by path string
     */
    public function nodes(): Collection
    {
        return collect($this->nodes);
    }

    /**
     * Get a node by its path.
     *
     * Retrieves a specific node using its dot-separated path string.
     * Returns null if the path doesn't exist in the configuration.
     *
     * @param  string    $path Dot-separated node path (e.g., "FI.production.posti")
     * @return null|Node The node at the specified path or null if not found
     */
    public function get(string $path): ?Node
    {
        return $this->nodes[$path] ?? null;
    }

    /**
     * Check if a node exists at the given path.
     *
     * @param  string $path Dot-separated node path to check
     * @return bool   True if a node exists at the path, false otherwise
     */
    public function has(string $path): bool
    {
        return array_key_exists($path, $this->nodes);
    }

    /**
     * Get all top-level partition nodes.
     *
     * Returns partition/tenant/namespace/division/entity nodes that form the
     * top level of the configuration hierarchy.
     *
     * @return Collection<string, Node> Partition nodes indexed by name
     */
    public function partitions(): Collection
    {
        return collect($this->partitions);
    }

    /**
     * Get a specific partition by name.
     *
     * @param  string    $name Partition identifier (e.g., "FI", "US")
     * @return null|Node The partition node or null if not found
     */
    public function partition(string $name): ?Node
    {
        return $this->partitions[$name] ?? null;
    }

    /**
     * Get all fallback/global/catchall nodes.
     *
     * Returns nodes that provide default values when no partition-specific
     * configuration matches the given context.
     *
     * @return Collection<string, Node> Fallback nodes indexed by name
     */
    public function fallbacks(): Collection
    {
        return collect($this->fallbacks);
    }

    /**
     * Get a specific fallback node by name.
     *
     * @param  string    $name Fallback identifier
     * @return null|Node The fallback node or null if not found
     */
    public function fallback(string $name): ?Node
    {
        return $this->fallbacks[$name] ?? null;
    }

    /**
     * Get nodes matching the given context filter.
     *
     * Filters nodes based on hierarchical position (partition, environment, provider, etc.).
     * Only nodes that match all specified context keys are returned.
     *
     * @param  array<string, string>    $context Context filter map (e.g., ['partition' => 'FI', 'environment' => 'production'])
     * @return Collection<string, Node> Nodes matching all context criteria
     */
    public function matching(array $context): Collection
    {
        return $this->nodes()->filter(
            fn (Node $node): bool => $node->matches($context),
        );
    }

    /**
     * Get nodes that have all of the specified tags.
     *
     * Filters nodes based on tag metadata. Only nodes containing all
     * specified tags are returned.
     *
     * @param  string                   ...$tags One or more tag identifiers
     * @return Collection<string, Node> Nodes containing all specified tags
     */
    public function tagged(string ...$tags): Collection
    {
        return $this->nodes()->filter(
            fn (Node $node): bool => $node->hasAllTags($tags),
        );
    }

    /**
     * Get nodes with expiration dates approaching within the specified timeframe.
     *
     * Filters nodes based on their `expires` metadata to identify credentials
     * that will expire soon and require attention or rotation.
     *
     * @param  int                      $days Number of days ahead to check for expiration (default: 30)
     * @return Collection<string, Node> Nodes expiring within the specified days
     */
    public function expiring(int $days = 30): Collection
    {
        return $this->nodes()->filter(
            fn (Node $node): bool => $node->isExpiring($days),
        );
    }

    /**
     * Get nodes that have already expired.
     *
     * Filters nodes where the `expires` date is in the past, indicating
     * credentials that are no longer valid and require immediate attention.
     *
     * @return Collection<string, Node> Nodes with past expiration dates
     */
    public function expired(): Collection
    {
        return $this->nodes()->filter(
            fn (Node $node): bool => $node->isExpired(),
        );
    }

    /**
     * Get nodes requiring credential rotation.
     *
     * Filters nodes based on their `rotated` metadata to identify credentials
     * that haven't been rotated recently and may pose a security risk.
     *
     * @param  int                      $days Maximum days since last rotation (default: 90)
     * @return Collection<string, Node> Nodes exceeding the rotation threshold
     */
    public function needsRotation(int $days = 90): Collection
    {
        return $this->nodes()->filter(
            fn (Node $node): bool => $node->needsRotation($days),
        );
    }

    /**
     * Get environment variable exports for a specific node.
     *
     * Returns the environment variable mappings defined in the node's export blocks,
     * with interpolated values resolved from node fields.
     *
     * @param  string                $path Dot-separated node path
     * @return array<string, string> Environment variable name-value pairs
     */
    public function exports(string $path): array
    {
        $node = $this->get($path);

        return $node?->export() ?? [];
    }

    /**
     * Get all exports from nodes matching the given context.
     *
     * Traverses fallbacks first to establish baseline exports, then partitions
     * to override with more specific values. Deeper hierarchical levels override
     * parent exports with the same key.
     *
     * @param array<string, string> $context Context filter map
     *
     * @return array<string, string> Merged environment variable exports
     */
    public function exportsForContext(array $context): array
    {
        $exports = [];

        // Process fallbacks first (without partition filter)
        $fallbackContext = $context;
        unset($fallbackContext['partition'], $fallbackContext['tenant']);

        foreach ($this->fallbacks as $fallback) {
            $exports = [...$exports, ...$this->collectExportsFromNode($fallback, $fallbackContext)];
        }

        // Then overlay partition-specific exports
        foreach ($this->partitions as $partition) {
            $exports = [...$exports, ...$this->collectExportsFromNode($partition, $context)];
        }

        return $exports;
    }

    /**
     * Get all exported environment variables from all nodes.
     *
     * @return array<string, string> Combined exports from all nodes
     */
    public function allExports(): array
    {
        $exports = [];

        foreach ($this->nodes as $node) {
            $exports = [...$exports, ...$node->export()];
        }

        return $exports;
    }

    /**
     * Collect exports from a node and its children matching context.
     *
     * @param  Node                  $node    The node to collect from
     * @param  array<string, string> $context The context filter
     * @return array<string, string> The collected exports
     */
    private function collectExportsFromNode(Node $node, array $context): array
    {
        $exports = [];

        // Check if node matches context at its level
        if (!$this->nodeMatchesContext($node, $context)) {
            return [];
        }

        // Add this node's exports
        $exports = [...$exports, ...$node->export()];

        // Recurse into children
        foreach ($node->children as $child) {
            $exports = [...$exports, ...$this->collectExportsFromNode($child, $context)];
        }

        return $exports;
    }

    /**
     * Check if a node matches the given context.
     *
     * @param  Node                  $node    The node
     * @param  array<string, string> $context The context
     * @return bool                  True if matches
     */
    private function nodeMatchesContext(Node $node, array $context): bool
    {
        // Map node types to context keys
        /** @var array<string, array<int, string>> $typeToContext */
        $typeToContext = [
            'partition' => ['partition', 'tenant', 'namespace', 'division', 'entity'],
            'environment' => ['environment'],
            'provider' => ['provider'],
            'country' => ['country'],
            'service' => ['service'],
        ];

        foreach ($context as $key => $value) {
            // Find the matching type for this context key
            foreach ($typeToContext as $nodeType => $contextKeys) {
                if (!in_array($key, $contextKeys, true)) {
                    continue;
                }

                // If node is this type, it must match the value
                if ($node->type === $nodeType && $node->name !== $value) {
                    return false;
                }

                // Check path for ancestor matching
                $position = match ($nodeType) {
                    'partition' => 0,
                    'environment' => 1,
                    'provider' => 2,
                    'country' => 3,
                    'service' => 4,
                    default => null,
                };

                if ($position !== null && isset($node->path[$position]) && $node->path[$position] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Build the config from the parsed AST.
     *
     * @param array<string, mixed> $ast Abstract syntax tree from parser
     */
    private function buildFromAst(array $ast): void
    {
        // Process partition-style blocks (partition/tenant/namespace/division/entity)
        /** @var array<int, array<string, mixed>> $partitions */
        $partitions = $ast['partitions'] ?? [];

        foreach ($partitions as $partitionData) {
            $node = $this->processPartition($partitionData);
            $this->partitions[$node->name] = $node;
            $this->registerNodeAndChildren($node);
        }

        // Process fallback blocks
        /** @var array<int, array<string, mixed>> $fallbacks */
        $fallbacks = $ast['fallbacks'] ?? [];

        foreach ($fallbacks as $fallbackData) {
            $node = $this->processFallback($fallbackData);
            $this->fallbacks[$node->name] = $node;
            $this->registerNodeAndChildren($node);
        }

        // Process group blocks (legacy format: group "name" "env" { credential "name" { ... } })
        // Groups with the same name but different environments need to be merged
        /** @var array<int, array<string, mixed>> $groups */
        $groups = $ast['groups'] ?? [];

        /** @var array<string, array<string, Node>> $groupedByName */
        $groupedByName = [];

        foreach ($groups as $groupData) {
            $result = $this->processGroupToEnvNode($groupData);
            $groupName = $result['groupName'];
            $envName = $result['envName'];
            $envNode = $result['envNode'];

            if (!isset($groupedByName[$groupName])) {
                $groupedByName[$groupName] = [];
            }

            $groupedByName[$groupName][$envName] = $envNode;
        }

        // Create partition nodes from grouped environments
        foreach ($groupedByName as $groupName => $environments) {
            $partitionNode = new Node(
                type: 'partition',
                name: $groupName,
                path: [$groupName],
                fields: [],
                exports: [],
                connections: [],
                tags: [],
                expires: null,
                rotated: null,
                owner: null,
                notes: null,
                children: $environments,
            );

            $this->partitions[$groupName] = $partitionNode;
            $this->registerNodeAndChildren($partitionNode);
        }
    }

    /**
     * Process a group block from the AST into an environment node.
     *
     * Group blocks use the format: group "name" "environment" { credential "name" { ... } }
     * This maps to the unified model as: partition > environment > service
     *
     * @param  array<string, mixed>                                     $groupData The group data
     * @return array{groupName: string, envName: string, envNode: Node} The processed data
     */
    private function processGroupToEnvNode(array $groupData): array
    {
        /** @var array<int, string> $labels */
        $labels = $groupData['labels'] ?? [];

        /** @var string $groupName */
        $groupName = $labels[0] ?? 'default';

        /** @var string $envName */
        $envName = $labels[1] ?? 'default';

        /** @var array<string, mixed> $body */
        $body = $groupData['body'] ?? [];

        // Extract group-level metadata
        $tags = $this->extractTags($body['tags'] ?? null);

        // Process credentials as service children
        $credentialChildren = [];

        /** @var array<int, array<string, mixed>> $credentialBlocks */
        $credentialBlocks = $body['credential'] ?? [];

        foreach ($credentialBlocks as $credentialBlock) {
            /** @var array<int, string> $credLabels */
            $credLabels = $credentialBlock['labels'] ?? [];

            /** @var string $credName */
            $credName = $credLabels[0] ?? 'default';

            /** @var array<string, mixed> $credBody */
            $credBody = $credentialBlock['body'] ?? [];

            $credPath = [$groupName, $envName, $credName];

            // Credentials inherit group-level tags
            $credentialNode = $this->processBlockWithTags('service', $credName, $credPath, $credBody, $tags);
            $credentialChildren[$credName] = $credentialNode;
        }

        // Create environment node with credentials as children
        $envNode = new Node(
            type: 'environment',
            name: $envName,
            path: [$groupName, $envName],
            fields: [],
            exports: [],
            connections: [],
            tags: $tags,
            expires: null,
            rotated: null,
            owner: null,
            notes: null,
            children: $credentialChildren,
        );

        return [
            'groupName' => $groupName,
            'envName' => $envName,
            'envNode' => $envNode,
        ];
    }

    /**
     * Register a node and all its children in the flat nodes collection.
     *
     * @param Node $node The node to register
     */
    private function registerNodeAndChildren(Node $node): void
    {
        $this->nodes[$node->pathString()] = $node;

        foreach ($node->children as $child) {
            $this->registerNodeAndChildren($child);
        }
    }

    /**
     * Process a partition from the AST.
     *
     * @param  array<string, mixed> $partitionData The partition data
     * @return Node                 The partition node
     */
    private function processPartition(array $partitionData): Node
    {
        /** @var array<int, string> $labels */
        $labels = $partitionData['labels'] ?? [];

        /** @var string $name */
        $name = $labels[0] ?? 'default';

        /** @var array<string, mixed> $body */
        $body = $partitionData['body'] ?? [];

        return $this->processBlock('partition', $name, [$name], $body);
    }

    /**
     * Process a fallback from the AST.
     *
     * @param  array<string, mixed> $fallbackData The fallback data
     * @return Node                 The fallback node
     */
    private function processFallback(array $fallbackData): Node
    {
        /** @var array<int, string> $labels */
        $labels = $fallbackData['labels'] ?? [];

        /** @var string $name */
        $name = $labels[0] ?? 'default';

        /** @var array<string, mixed> $body */
        $body = $fallbackData['body'] ?? [];

        return $this->processBlock('fallback', $name, [$name], $body);
    }

    /**
     * Process a generic block into a Node.
     *
     * @param  string               $type       Block type
     * @param  string               $name       Block name
     * @param  array<string>        $parentPath Parent path segments
     * @param  array<string, mixed> $body       Block body
     * @return Node                 The built node
     */
    private function processBlock(string $type, string $name, array $parentPath, array $body): Node
    {
        // Extract fields and special blocks
        $fields = $this->extractFieldsFromBody($body);

        /** @var array<int, array<string, mixed>> $exportBlocks */
        $exportBlocks = $body['export'] ?? [];

        /** @var array<int, array<string, mixed>> $connectBlocks */
        $connectBlocks = $body['connect'] ?? [];

        $exports = $this->extractExports($exportBlocks);
        $connections = $this->extractConnections($connectBlocks);
        $tags = $this->extractTags($body['tags'] ?? null);

        // Process child blocks
        $children = [];

        // Environment blocks
        /** @var array<int, array<string, mixed>> $envBlocks */
        $envBlocks = $body['environment'] ?? [];

        foreach ($envBlocks as $envBlock) {
            /** @var array<int, string> $envLabels */
            $envLabels = $envBlock['labels'] ?? [];

            /** @var array<string, mixed> $envBody */
            $envBody = $envBlock['body'] ?? [];

            // Support multiple environment labels (e.g., environment "staging" "local")
            foreach ($envLabels as $envName) {
                $envPath = [...$parentPath, $envName];
                $child = $this->processBlock('environment', $envName, $envPath, $envBody);
                $children[$envName] = $child;
            }
        }

        // Provider blocks
        /** @var array<int, array<string, mixed>> $providerBlocks */
        $providerBlocks = $body['provider'] ?? [];

        foreach ($providerBlocks as $providerBlock) {
            /** @var array<int, string> $providerLabels */
            $providerLabels = $providerBlock['labels'] ?? [];

            /** @var string $providerName */
            $providerName = $providerLabels[0] ?? 'default';

            /** @var array<string, mixed> $providerBody */
            $providerBody = $providerBlock['body'] ?? [];

            $providerPath = [...$parentPath, $providerName];
            $child = $this->processBlock('provider', $providerName, $providerPath, $providerBody);
            $children[$providerName] = $child;
        }

        // Country blocks
        /** @var array<int, array<string, mixed>> $countryBlocks */
        $countryBlocks = $body['country'] ?? [];

        foreach ($countryBlocks as $countryBlock) {
            /** @var array<int, string> $countryLabels */
            $countryLabels = $countryBlock['labels'] ?? [];

            /** @var string $countryName */
            $countryName = $countryLabels[0] ?? 'default';

            /** @var array<string, mixed> $countryBody */
            $countryBody = $countryBlock['body'] ?? [];

            $countryPath = [...$parentPath, $countryName];
            $child = $this->processBlock('country', $countryName, $countryPath, $countryBody);
            $children[$countryName] = $child;
        }

        // Service blocks
        /** @var array<int, array<string, mixed>> $serviceBlocks */
        $serviceBlocks = $body['service'] ?? [];

        foreach ($serviceBlocks as $serviceBlock) {
            /** @var array<int, string> $serviceLabels */
            $serviceLabels = $serviceBlock['labels'] ?? [];

            /** @var string $serviceName */
            $serviceName = $serviceLabels[0] ?? 'default';

            /** @var array<string, mixed> $serviceBody */
            $serviceBody = $serviceBlock['body'] ?? [];

            $servicePath = [...$parentPath, $serviceName];
            $child = $this->processBlock('service', $serviceName, $servicePath, $serviceBody);
            $children[$serviceName] = $child;
        }

        return new Node(
            type: $type,
            name: $name,
            path: $parentPath,
            fields: $fields,
            exports: $exports,
            connections: $connections,
            tags: $tags,
            expires: $this->extractScalarValue($body['expires'] ?? null),
            rotated: $this->extractScalarValue($body['rotated'] ?? null),
            owner: $this->extractScalarValue($body['owner'] ?? null),
            notes: $this->extractScalarValue($body['notes'] ?? null),
            children: $children,
        );
    }

    /**
     * Process a nested block with inherited tags from parent.
     *
     * Similar to processBlock but merges inherited tags from parent with any
     * tags defined on the block itself.
     *
     * @param  string               $type          Block type
     * @param  string               $name          Block name
     * @param  array<string>        $parentPath    Parent path segments
     * @param  array<string, mixed> $body          Block body
     * @param  array<string>        $inheritedTags Tags inherited from parent
     * @return Node                 The built node
     */
    private function processBlockWithTags(string $type, string $name, array $parentPath, array $body, array $inheritedTags): Node
    {
        // Extract fields and special blocks
        $fields = $this->extractFieldsFromBody($body);

        /** @var array<int, array<string, mixed>> $exportBlocks */
        $exportBlocks = $body['export'] ?? [];

        /** @var array<int, array<string, mixed>> $connectBlocks */
        $connectBlocks = $body['connect'] ?? [];

        $exports = $this->extractExports($exportBlocks);
        $connections = $this->extractConnections($connectBlocks);

        // Merge inherited tags with any tags defined on this block
        $ownTags = $this->extractTags($body['tags'] ?? null);
        $tags = array_unique([...$inheritedTags, ...$ownTags]);

        return new Node(
            type: $type,
            name: $name,
            path: $parentPath,
            fields: $fields,
            exports: $exports,
            connections: $connections,
            tags: $tags,
            expires: $this->extractScalarValue($body['expires'] ?? null),
            rotated: $this->extractScalarValue($body['rotated'] ?? null),
            owner: $this->extractScalarValue($body['owner'] ?? null),
            notes: $this->extractScalarValue($body['notes'] ?? null),
            children: [],
        );
    }

    /**
     * Extract fields from a block body (excluding nested blocks and exports).
     *
     * @param  array<string, mixed> $body The block body
     * @return array<string, mixed> The extracted fields
     */
    private function extractFieldsFromBody(array $body): array
    {
        $excludeKeys = [
            'export', 'connect', 'environment', 'provider', 'country', 'service',
            'tags', 'expires', 'rotated', 'owner', 'notes',
        ];
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
     * @param  mixed $value AST node value
     * @return mixed Resolved PHP value
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
     * Resolve a function call from the AST.
     *
     * @param  array<string, mixed> $func Function AST node
     * @return mixed                Resolved function result
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

        return $func;
    }

    /**
     * Resolve a reference (e.g., self.host).
     *
     * @param  array<string, mixed> $ref Reference AST node
     * @return string               Interpolation string
     */
    private function resolveReference(array $ref): string
    {
        /** @var array<int, string> $parts */
        $parts = $ref['parts'] ?? [];

        return '${'.implode('.', $parts).'}';
    }
}
