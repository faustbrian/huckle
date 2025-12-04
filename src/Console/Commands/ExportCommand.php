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
 * Artisan command to export credentials as environment variables.
 *
 * Provides CLI interface for exporting credentials in various formats suitable
 * for environment variable loading. Supports dotenv (.env), JSON, and shell
 * export formats with filtering by path, group, environment, and tags. Useful
 * for integrating credentials into application deployments and CI/CD pipelines.
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
     * Retrieves credentials based on filters and exports them in the specified
     * format. Returns FAILURE if no credentials match the criteria, SUCCESS
     * when credentials are successfully exported to stdout.
     *
     * @param HuckleManager $huckle The Huckle manager instance
     *
     * @return int FAILURE if no credentials found, SUCCESS otherwise
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
     * Retrieves credentials matching the specified filters and returns their
     * export key-value pairs. If a specific path is provided, returns exports
     * for that credential only. Otherwise, applies group, environment, and tag
     * filters to select matching credentials.
     *
     * @param HuckleManager $huckle The Huckle manager instance
     * @param null|string   $path   Specific credential path to export
     * @param null|string   $group  Group name filter
     * @param null|string   $env    Environment name filter
     * @param array<string> $tags   Tag filters (must match all)
     *
     * @return array<string, string> Key-value pairs for environment variables
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
     * Converts exports to dotenv format with proper value escaping. Values
     * containing whitespace, quotes, or hash symbols are quoted and escaped.
     *
     * @param array<string, string> $exports The key-value pairs to export
     *
     * @return string The formatted dotenv output
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
     * Converts exports to pretty-printed JSON format suitable for programmatic
     * consumption or further processing.
     *
     * @param array<string, string> $exports The key-value pairs to export
     *
     * @return string The formatted JSON output
     */
    private function formatJson(array $exports): string
    {
        return json_encode($exports, JSON_PRETTY_PRINT) ?: '{}';
    }

    /**
     * Format as shell export statements.
     *
     * Converts exports to shell export format with proper single-quote escaping
     * for safe execution in shell environments. Output can be sourced directly.
     *
     * @param array<string, string> $exports The key-value pairs to export
     *
     * @return string The formatted shell export statements
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
     * Wraps values in double quotes and escapes special characters if the value
     * contains whitespace, hash symbols, double quotes, or single quotes.
     *
     * @param string $value The value to escape
     *
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
     * Wraps values in single quotes and escapes embedded single quotes using
     * the '\'' escape sequence for safe shell evaluation.
     *
     * @param string $value The value to escape
     *
     * @return string The escaped value wrapped in single quotes
     */
    private function escapeShellValue(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
