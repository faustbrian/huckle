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
 * Show detailed credential information.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ShowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:show
        {path : The credential path (e.g., database.production.main)}
        {--reveal : Reveal sensitive values}
        {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show detailed credential information';

    /**
     * Execute the console command.
     *
     * @param  HuckleManager $huckle The Huckle manager instance
     * @return int           The exit code
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var string $path */
        $path = $this->argument('path');
        $reveal = $this->option('reveal');
        $json = $this->option('json');
        $maskSensitive = Config::get('huckle.mask_sensitive', true);

        $credential = $huckle->get($path);

        if (!$credential instanceof Credential) {
            $this->error('Credential not found: '.$path);

            return self::FAILURE;
        }

        // Build data array
        $data = [
            'path' => $credential->path(),
            'group' => $credential->group,
            'environment' => $credential->environment,
            'name' => $credential->name,
            'tags' => $credential->tags,
            'expires' => $credential->expires,
            'rotated' => $credential->rotated,
            'owner' => $credential->owner,
            'notes' => $credential->notes,
            'fields' => [],
            'exports' => [],
            'connections' => [],
        ];

        // Process fields
        foreach ($credential->fields as $key => $value) {
            if ($value instanceof SensitiveValue) {
                $data['fields'][$key] = ($reveal || !$maskSensitive)
                    ? $value->reveal()
                    : $value->masked();
            } else {
                $data['fields'][$key] = $value;
            }
        }

        // Process exports
        foreach ($credential->exports as $key => $value) {
            $data['exports'][$key] = $value;
        }

        // Process connections
        foreach ($credential->connectionNames() as $name) {
            $data['connections'][$name] = $credential->connection($name);
        }

        // Output as JSON
        if ($json) {
            $encoded = json_encode($data, JSON_PRETTY_PRINT);
            $this->line($encoded !== false ? $encoded : '{}');

            return self::SUCCESS;
        }

        // Output as formatted text
        $this->info('Credential: '.$credential->path());
        $this->newLine();

        // Metadata
        $this->line('<comment>Group:</comment>       '.$credential->group);
        $this->line('<comment>Environment:</comment> '.$credential->environment);
        $this->line('<comment>Tags:</comment>        '.($credential->tags === [] ? '-' : implode(', ', $credential->tags)));
        $this->line('<comment>Owner:</comment>       '.($credential->owner ?? '-'));
        $this->line('<comment>Expires:</comment>     '.($credential->expires ?? '-'));
        $this->line('<comment>Rotated:</comment>     '.($credential->rotated ?? 'never'));

        if ($credential->notes) {
            $this->newLine();
            $this->line('<comment>Notes:</comment>       '.$credential->notes);
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

        if ($credential->isExpired()) {
            $this->error('⚠ This credential has EXPIRED!');
        } elseif ($credential->isExpiring(30)) {
            $this->warn('⚠ This credential is expiring soon');
        }

        if ($credential->needsRotation(90)) {
            $this->warn('⚠ This credential needs rotation');
        }

        return self::SUCCESS;
    }
}
