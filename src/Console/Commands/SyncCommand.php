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

use function addslashes;
use function base_path;
use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function mb_trim;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;

/**
 * Synchronizes Huckle credentials to Laravel .env configuration file.
 *
 * Exports credential values to .env format, supporting both merge and replace
 * modes. Provides filtering by group and environment, dry-run preview, and
 * automatic value escaping for shell-safe environment variable formatting.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Supports custom .env file paths, replacement mode, dry-run preview, and
     * credential filtering by group or environment for selective synchronization.
     *
     * @var string
     */
    protected $signature = 'huckle:sync
        {--path= : Path to .env file (defaults to base .env)}
        {--replace : Replace entire .env instead of merging}
        {--dry-run : Show what would be changed without writing}
        {--group= : Filter by group name}
        {--env= : Filter by environment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync credentials to .env file';

    /**
     * Execute the console command.
     *
     * Retrieves filtered credentials, exports environment variables, merges or
     * replaces existing .env content, and writes the result. In dry-run mode,
     * displays changes without modifying the file.
     *
     * @param HuckleManager $huckle The Huckle manager instance for credential retrieval
     *
     * @return int Command exit code (SUCCESS or FAILURE)
     */
    public function handle(HuckleManager $huckle): int
    {
        $pathOption = $this->option('path');

        /** @var string $envPath */
        $envPath = $pathOption ?? base_path('.env');
        $replace = $this->option('replace');
        $dryRun = $this->option('dry-run');

        /** @var null|string $group */
        $group = $this->option('group');

        /** @var null|string $env */
        $env = $this->option('env');

        // Get exports
        $credentials = $huckle->credentials();

        if ($group !== null) {
            $credentials = $credentials->filter(fn ($c): bool => $c->group === $group);
        }

        if ($env !== null) {
            $credentials = $credentials->filter(fn ($c): bool => $c->environment === $env);
        }

        $exports = [];

        foreach ($credentials as $credential) {
            $exports = [...$exports, ...$credential->export()];
        }

        if ($exports === []) {
            $this->warn('No credentials found to sync.');

            return self::FAILURE;
        }

        // Read existing .env
        $existingEnv = [];

        if (!$replace && file_exists($envPath)) {
            $existingEnv = $this->parseEnvFile($envPath);
        }

        // Merge or replace
        $newEnv = $replace ? $exports : [...$existingEnv, ...$exports];

        // Generate output
        $content = $this->generateEnvContent($newEnv);

        if ($dryRun) {
            $this->info('Dry run - would write:');
            $this->line($content);

            $this->newLine();
            $this->info('Changes:');

            foreach ($exports as $key => $value) {
                $old = $existingEnv[$key] ?? '<not set>';
                $new = $value;

                if ($old === $new) {
                    continue;
                }

                $this->line(sprintf('  %s: %s -> %s', $key, $old, $new));
            }

            return self::SUCCESS;
        }

        // Write .env
        file_put_contents($envPath, $content);

        $this->info('Synced '.count($exports).(' environment variables to '.$envPath));

        return self::SUCCESS;
    }

    /**
     * Parse an existing .env file into key-value pairs.
     *
     * Reads the .env file line by line, skipping comments and empty lines,
     * extracting KEY=value pairs while handling quoted values and whitespace.
     *
     * @param string $path Absolute path to the .env file to parse
     *
     * @return array<string, string> Associative array of environment variable names and values
     */
    private function parseEnvFile(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return [];
        }

        $result = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = mb_trim($line);

            // Skip comments and empty lines
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=value
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = mb_trim($key);
            $value = mb_trim($value, " \t\n\r\0\x0B\"'");
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Generate .env file content from key-value pairs.
     *
     * Converts an associative array into properly formatted .env file content,
     * automatically escaping values that contain special characters.
     *
     * @param array<string, string> $values Associative array of environment variable names and values
     *
     * @return string Complete .env file content with newline-separated entries
     */
    private function generateEnvContent(array $values): string
    {
        $lines = [];

        foreach ($values as $key => $value) {
            $escapedValue = $this->escapeValue($value);
            $lines[] = sprintf('%s=%s', $key, $escapedValue);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Escape a value for .env format.
     *
     * Wraps values containing whitespace, quotes, or hash characters in double
     * quotes and applies backslash escaping to ensure proper shell parsing.
     *
     * @param string $value The raw value to escape
     *
     * @return string The escaped and optionally quoted value safe for .env files
     */
    private function escapeValue(string $value): string
    {
        if (preg_match('/[\s#"\']/', $value)) {
            return '"'.addslashes($value).'"';
        }

        return $value;
    }
}
