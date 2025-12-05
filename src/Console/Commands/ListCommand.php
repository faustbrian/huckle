<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\Node;
use Illuminate\Console\Command;

use const JSON_PRETTY_PRINT;

use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function sprintf;

/**
 * Artisan command to list all nodes.
 *
 * Provides CLI interface for listing and filtering nodes stored in Huckle.
 * Supports filtering by partition, environment, and tags with output in either
 * human-readable table format or JSON for programmatic consumption. Useful for
 * discovering available nodes and auditing configuration inventory.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:list
        {--partition= : Filter by partition name}
        {--environment=* : Filter by environment(s)}
        {--tag=* : Filter by tags}
        {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all nodes';

    /**
     * Execute the console command.
     *
     * Retrieves nodes from Huckle, applies filters if specified, and
     * displays them in either table or JSON format. Returns SUCCESS even if
     * no nodes match the filter criteria.
     *
     * @param HuckleManager $huckle The Huckle manager instance
     *
     * @return int Command exit status (always SUCCESS)
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var null|string $partition */
        $partition = $this->option('partition');

        /** @var null|array<string>|string $envInput */
        $envInput = $this->option('environment');

        /** @var array<string> $envs */
        $envs = match (true) {
            is_array($envInput) => $envInput,
            is_string($envInput) => [$envInput],
            default => [],
        };

        /** @var array<string> $tags */
        $tags = $this->option('tag');
        $json = $this->option('json');

        // Get nodes - filter to only show leaf nodes (not partition/environment containers)
        $nodes = $huckle->nodes()->reject(
            fn (Node $n): bool => in_array($n->type, ['partition', 'environment'], true),
        );

        // Apply filters
        if ($partition !== null) {
            $nodes = $nodes->filter(fn (Node $n): bool => isset($n->path[0]) && $n->path[0] === $partition);
        }

        if ($envs !== []) {
            $nodes = $nodes->filter(fn (Node $n): bool => isset($n->path[1]) && in_array($n->path[1], $envs, true));
        }

        if ($tags !== []) {
            $nodes = $nodes->filter(fn (Node $n): bool => $n->hasAllTags($tags));
        }

        if ($nodes->isEmpty()) {
            $this->warn('No nodes found matching the criteria.');

            return self::SUCCESS;
        }

        // Output as JSON
        if ($json) {
            $data = $nodes->map(fn (Node $n): array => [
                'path' => $n->pathString(),
                'type' => $n->type,
                'name' => $n->name,
                'tags' => $n->tags,
                'expires' => $n->expires,
                'rotated' => $n->rotated,
                'owner' => $n->owner,
            ])->values()->all();

            $encoded = json_encode($data, JSON_PRETTY_PRINT);
            $this->line($encoded !== false ? $encoded : '[]');

            return self::SUCCESS;
        }

        // Output as table
        $rows = $nodes->map(fn (Node $n): array => [
            $n->pathString(),
            $n->type,
            implode(', ', $n->tags),
            $n->expires ?? '-',
            $n->owner ?? '-',
        ])->values()->all();

        $this->table(
            ['Path', 'Type', 'Tags', 'Expires', 'Owner'],
            $rows,
        );

        $this->newLine();
        $this->info(sprintf('Total: %d node(s)', $nodes->count()));

        return self::SUCCESS;
    }
}
