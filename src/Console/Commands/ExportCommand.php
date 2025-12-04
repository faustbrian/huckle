<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\HuckleManager;
use Illuminate\Console\Command;

use const JSON_PRETTY_PRINT;

use function addslashes;
use function implode;
use function json_encode;
use function preg_match;
use function sprintf;
use function str_replace;

/**
 * Export credentials as environment variables.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:export
        {path? : The credential path to export (exports all if omitted)}
        {--format=dotenv : Output format (dotenv, json, shell)}
        {--group= : Filter by group name}
        {--env= : Filter by environment}
        {--tag=* : Filter by tags}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export credentials as environment variables';

    /**
     * Execute the console command.
     *
     * @param  HuckleManager $huckle The Huckle manager instance
     * @return int           The exit code
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var null|string $path */
        $path = $this->argument('path');

        /** @var string $format */
        $format = $this->option('format');

        /** @var null|string $group */
        $group = $this->option('group');

        /** @var null|string $env */
        $env = $this->option('env');

        /** @var array<string> $tags */
        $tags = $this->option('tag');

        $exports = $this->getExports($huckle, $path, $group, $env, $tags);

        if ($exports === []) {
            $this->warn('No credentials found matching the criteria.');

            return self::FAILURE;
        }

        $output = match ($format) {
            'json' => $this->formatJson($exports),
            'shell' => $this->formatShell($exports),
            default => $this->formatDotenv($exports),
        };

        $this->line($output);

        return self::SUCCESS;
    }

    /**
     * Get exports based on filters.
     *
     * @param  HuckleManager         $huckle The manager
     * @param  null|string           $path   Specific path
     * @param  null|string           $group  Group filter
     * @param  null|string           $env    Environment filter
     * @param  array<string>         $tags   Tag filters
     * @return array<string, string> The exports
     */
    private function getExports(
        HuckleManager $huckle,
        ?string $path,
        ?string $group,
        ?string $env,
        array $tags,
    ): array {
        // Specific path
        if ($path !== null) {
            return $huckle->exports($path);
        }

        // Filter credentials
        $credentials = $huckle->credentials();

        if ($group !== null) {
            $credentials = $credentials->filter(fn ($c): bool => $c->group === $group);
        }

        if ($env !== null) {
            $credentials = $credentials->filter(fn ($c): bool => $c->environment === $env);
        }

        if ($tags !== []) {
            $credentials = $credentials->filter(fn ($c): bool => $c->hasAllTags($tags));
        }

        // Collect exports
        $exports = [];

        foreach ($credentials as $credential) {
            $exports = [...$exports, ...$credential->export()];
        }

        return $exports;
    }

    /**
     * Format as .env format.
     *
     * @param  array<string, string> $exports The exports
     * @return string                The formatted output
     */
    private function formatDotenv(array $exports): string
    {
        $lines = [];

        foreach ($exports as $key => $value) {
            $escapedValue = $this->escapeValue($value);
            $lines[] = sprintf('%s=%s', $key, $escapedValue);
        }

        return implode("\n", $lines);
    }

    /**
     * Format as JSON.
     *
     * @param  array<string, string> $exports The exports
     * @return string                The formatted output
     */
    private function formatJson(array $exports): string
    {
        return json_encode($exports, JSON_PRETTY_PRINT) ?: '{}';
    }

    /**
     * Format as shell export statements.
     *
     * @param  array<string, string> $exports The exports
     * @return string                The formatted output
     */
    private function formatShell(array $exports): string
    {
        $lines = [];

        foreach ($exports as $key => $value) {
            $escapedValue = $this->escapeShellValue($value);
            $lines[] = sprintf('export %s=%s', $key, $escapedValue);
        }

        return implode("\n", $lines);
    }

    /**
     * Escape a value for .env format.
     *
     * @param  string $value The value to escape
     * @return string The escaped value
     */
    private function escapeValue(string $value): string
    {
        if (preg_match('/[\s#"\']/', $value)) {
            return '"'.addslashes($value).'"';
        }

        return $value;
    }

    /**
     * Escape a value for shell export.
     *
     * @param  string $value The value to escape
     * @return string The escaped value
     */
    private function escapeShellValue(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
