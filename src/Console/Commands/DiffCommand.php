<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Support\SensitiveValue;
use Illuminate\Console\Command;

use const JSON_PRETTY_PRINT;

use function count;
use function is_array;
use function is_scalar;
use function json_encode;
use function sprintf;

/**
 * Artisan command to compare nodes between environments.
 *
 * Provides CLI interface for comparing nodes across different environments
 * to identify missing nodes, extra nodes, and field-level differences.
 * Helps ensure consistency and identify configuration drift between environments
 * like staging, production, and development.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DiffCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:diff
        {env1 : First environment to compare}
        {env2 : Second environment to compare}
        {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare nodes between two environments';

    /**
     * Execute the console command.
     *
     * Compares nodes between two environments and displays the differences
     * in either human-readable format or JSON. Shows nodes unique to each
     * environment and field-level differences for nodes that exist in both.
     *
     * @param HuckleManager $huckle The Huckle manager instance
     *
     * @return int Command exit status (always SUCCESS)
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var string $env1 */
        $env1 = $this->argument('env1');

        /** @var string $env2 */
        $env2 = $this->argument('env2');
        $json = $this->option('json');

        $diff = $huckle->diff($env1, $env2);

        // Output as JSON
        if ($json) {
            $encoded = json_encode($diff, JSON_PRETTY_PRINT);
            $this->line($encoded !== false ? $encoded : '{}');

            return self::SUCCESS;
        }

        // Output formatted
        $this->info(sprintf('Comparing environments: %s vs %s', $env1, $env2));
        $this->newLine();

        // Only in env1
        /** @var array<string> $only1 */
        $only1 = $diff['only_in_'.$env1] ?? [];

        if ($only1 !== []) {
            $this->comment(sprintf('Only in %s:', $env1));

            foreach ($only1 as $path) {
                $this->line('  + '.$path);
            }

            $this->newLine();
        }

        // Only in env2
        /** @var array<string> $only2 */
        $only2 = $diff['only_in_'.$env2] ?? [];

        if ($only2 !== []) {
            $this->comment(sprintf('Only in %s:', $env2));

            foreach ($only2 as $path) {
                $this->line('  + '.$path);
            }

            $this->newLine();
        }

        // Differences
        /** @var array<string, array<string, array<string, mixed>>> $differences */
        $differences = $diff['differences'] ?? [];

        if ($differences !== []) {
            $this->comment('Field differences:');

            foreach ($differences as $path => $fields) {
                $this->line(sprintf('  %s:', $path));

                foreach ($fields as $field => $values) {
                    $v1 = $this->formatValue($values[$env1] ?? '<not set>');
                    $v2 = $this->formatValue($values[$env2] ?? '<not set>');
                    $this->line(sprintf('    %s:', $field));
                    $this->line(sprintf('      %s: %s', $env1, $v1));
                    $this->line(sprintf('      %s: %s', $env2, $v2));
                }
            }

            $this->newLine();
        }

        // Summary
        $totalDiff = count($only1) + count($only2) + count($differences);

        if ($totalDiff === 0) {
            $this->info('✓ Environments are identical');
        } else {
            $this->warn(sprintf('Found %d difference(s)', $totalDiff));
        }

        return self::SUCCESS;
    }

    /**
     * Format a value for display.
     *
     * Converts various value types into human-readable strings for diff output.
     * Handles SensitiveValue objects by revealing their content, converts arrays
     * to JSON, and provides special representations for null and complex types.
     *
     * @param mixed $value The value to format
     *
     * @return string The formatted value as a display string
     */
    private function formatValue(mixed $value): string
    {
        if ($value instanceof SensitiveValue) {
            return $value->reveal();
        }

        if (is_array($value)) {
            $encoded = json_encode($value);

            return $encoded !== false ? $encoded : '[]';
        }

        if ($value === null) {
            return '<null>';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '<complex>';
    }
}
