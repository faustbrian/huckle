<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\Credential;
use Illuminate\Console\Command;

use const JSON_PRETTY_PRINT;

use function implode;
use function json_encode;
use function sprintf;

/**
 * Artisan command to list all credentials.
 *
 * Provides CLI interface for listing and filtering credentials stored in Huckle.
 * Supports filtering by group, environment, and tags with output in either
 * human-readable table format or JSON for programmatic consumption. Useful for
 * discovering available credentials and auditing credential inventory.
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
        {--group= : Filter by group name}
        {--env= : Filter by environment}
        {--tag=* : Filter by tags}
        {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all credentials';

    /**
     * Execute the console command.
     *
     * Retrieves credentials from Huckle, applies filters if specified, and
     * displays them in either table or JSON format. Returns SUCCESS even if
     * no credentials match the filter criteria.
     *
     * @param HuckleManager $huckle The Huckle manager instance
     *
     * @return int Command exit status (always SUCCESS)
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var null|string $group */
        $group = $this->option('group');

        /** @var null|string $env */
        $env = $this->option('env');

        /** @var array<string> $tags */
        $tags = $this->option('tag');
        $json = $this->option('json');

        // Get credentials
        $credentials = $huckle->credentials();

        // Apply filters
        if ($group !== null) {
            $credentials = $credentials->filter(fn (Credential $c): bool => $c->group === $group);
        }

        if ($env !== null) {
            $credentials = $credentials->filter(fn (Credential $c): bool => $c->environment === $env);
        }

        if ($tags !== []) {
            $credentials = $credentials->filter(fn (Credential $c): bool => $c->hasAllTags($tags));
        }

        if ($credentials->isEmpty()) {
            $this->warn('No credentials found matching the criteria.');

            return self::SUCCESS;
        }

        // Output as JSON
        if ($json) {
            $data = $credentials->map(fn (Credential $c): array => [
                'path' => $c->path(),
                'group' => $c->group,
                'environment' => $c->environment,
                'name' => $c->name,
                'tags' => $c->tags,
                'expires' => $c->expires,
                'rotated' => $c->rotated,
                'owner' => $c->owner,
            ])->values()->all();

            $encoded = json_encode($data, JSON_PRETTY_PRINT);
            $this->line($encoded !== false ? $encoded : '[]');

            return self::SUCCESS;
        }

        // Output as table
        $rows = $credentials->map(fn (Credential $c): array => [
            $c->path(),
            implode(', ', $c->tags),
            $c->expires ?? '-',
            $c->owner ?? '-',
        ])->values()->all();

        $this->table(
            ['Path', 'Tags', 'Expires', 'Owner'],
            $rows,
        );

        $this->newLine();
        $this->info(sprintf('Total: %d credential(s)', $credentials->count()));

        return self::SUCCESS;
    }
}
