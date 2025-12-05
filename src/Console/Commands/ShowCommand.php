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
use Cline\Huckle\Support\SensitiveValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use const JSON_PRETTY_PRINT;

use function array_keys;
use function implode;
use function is_array;
use function is_scalar;
use function json_encode;
use function sprintf;

/**
 * Displays detailed node information from the Huckle configuration.
 *
 * Retrieves and formats node metadata, fields, exports, and connection
 * strings. Supports multiple output formats (text/JSON) and optional sensitive
 * value masking. Provides expiration and rotation warnings for lifecycle
 * management.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ShowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Accepts a node path in dot notation (e.g., FI.production.posti)
     * and supports optional flags for revealing sensitive data and JSON output.
     *
     * @var string
     */
    protected $signature = 'huckle:show
        {path : The node path (e.g., FI.production.posti)}
        {--reveal : Reveal sensitive values}
        {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show detailed node information';

    /**
     * Execute the console command.
     *
     * Retrieves the node from storage, processes sensitive values based on
     * configuration and flags, then outputs formatted information. Returns failure
     * if the node is not found.
     *
     * @param HuckleManager $huckle The Huckle manager instance for node retrieval and storage access
     *
     * @return int Command exit code (0 for SUCCESS, 1 for FAILURE)
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var string $path */
        $path = $this->argument('path');
        $reveal = $this->option('reveal');
        $json = $this->option('json');
        $maskSensitive = Config::get('huckle.mask_sensitive', true);

        $node = $huckle->get($path);

        if (!$node instanceof Node) {
            $this->error('Node not found: '.$path);

            return self::FAILURE;
        }

        // Build data array
        $data = [
            'path' => $node->pathString(),
            'type' => $node->type,
            'name' => $node->name,
            'tags' => $node->tags,
            'expires' => $node->expires,
            'rotated' => $node->rotated,
            'owner' => $node->owner,
            'notes' => $node->notes,
            'fields' => [],
            'exports' => [],
            'connections' => [],
        ];

        // Process fields
        foreach ($node->fields as $key => $value) {
            if ($value instanceof SensitiveValue) {
                $data['fields'][$key] = ($reveal || !$maskSensitive)
                    ? $value->reveal()
                    : $value->masked();
            } else {
                $data['fields'][$key] = $value;
            }
        }

        // Process exports
        foreach ($node->exports as $key => $value) {
            $data['exports'][$key] = $value;
        }

        // Process connections
        foreach ($node->connectionNames() as $name) {
            $data['connections'][$name] = $node->connection($name);
        }

        // Output as JSON
        if ($json) {
            $encoded = json_encode($data, JSON_PRETTY_PRINT);
            $this->line($encoded !== false ? $encoded : '{}');

            return self::SUCCESS;
        }

        // Output as formatted text
        $this->info('Node: '.$node->pathString());
        $this->newLine();

        // Metadata
        $this->line('<comment>Type:</comment>        '.$node->type);
        $this->line('<comment>Name:</comment>        '.$node->name);
        $this->line('<comment>Tags:</comment>        '.($node->tags === [] ? '-' : implode(', ', $node->tags)));
        $this->line('<comment>Owner:</comment>       '.($node->owner ?? '-'));
        $this->line('<comment>Expires:</comment>     '.($node->expires ?? '-'));
        $this->line('<comment>Rotated:</comment>     '.($node->rotated ?? 'never'));

        if ($node->notes) {
            $this->newLine();
            $this->line('<comment>Notes:</comment>       '.$node->notes);
        }

        // Fields
        if ($data['fields'] !== []) {
            $this->newLine();
            $this->info('Fields:');

            foreach ($data['fields'] as $key => $value) {
                if (is_array($value)) {
                    $encoded = json_encode($value);
                    $displayValue = $encoded !== false ? $encoded : '[]';
                } elseif (is_scalar($value)) {
                    $displayValue = (string) $value;
                } else {
                    $displayValue = '<complex>';
                }

                $this->line(sprintf('  %s: %s', $key, $displayValue));
            }
        }

        // Exports
        if ($data['exports'] !== []) {
            $this->newLine();
            $this->info('Exports:');

            foreach (array_keys($data['exports']) as $key) {
                $this->line('  '.$key);
            }
        }

        // Connections
        if ($data['connections'] !== []) {
            $this->newLine();
            $this->info('Connections:');

            foreach ($data['connections'] as $name => $command) {
                $this->line(sprintf('  %s: %s', $name, $command));
            }
        }

        // Status warnings
        $this->newLine();

        if ($node->isExpired()) {
            $this->error('⚠ This node has EXPIRED!');
        } elseif ($node->isExpiring(30)) {
            $this->warn('⚠ This node is expiring soon');
        }

        if ($node->needsRotation(90)) {
            $this->warn('⚠ This node needs rotation');
        }

        return self::SUCCESS;
    }
}
