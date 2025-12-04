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
use Symfony\Component\Process\Process;
use Throwable;

use const PHP_OS_FAMILY;

use function implode;
use function sprintf;

/**
 * Artisan command to execute connection commands for credentials.
 *
 * Provides CLI interface for connecting to services using credentials
 * stored in Huckle. Supports listing available connections, executing
 * connection commands interactively, and copying commands to clipboard
 * for manual execution. Connection commands can be SSH, database clients,
 * or any shell command configured in the credential's connection block.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConnectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:connect
        {path : The credential path (e.g., database.production.main)}
        {connection? : The connection name (e.g., psql, ssh)}
        {--list : List available connections instead of executing}
        {--copy : Copy command to clipboard instead of executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute or display connection commands for credentials';

    /**
     * Execute the console command.
     *
     * Retrieves the credential from the specified path and either lists
     * available connections, copies a connection command to clipboard, or
     * executes the connection command in an interactive TTY session.
     *
     * @param HuckleManager $huckle The Huckle manager instance
     *
     * @return int Command exit status (SUCCESS or FAILURE)
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        /** @var null|string $connectionName */
        $connectionName = $this->argument('connection');

        $credential = $huckle->get($path);

        if (!$credential instanceof Credential) {
            $this->error('Credential not found: '.$path);

            return self::FAILURE;
        }

        $connections = $credential->connectionNames();

        // List available connections
        if ($this->option('list') || $connectionName === null) {
            if ($connections === []) {
                $this->warn('No connections defined for: '.$path);

                return self::FAILURE;
            }

            $this->info(sprintf('Available connections for %s:', $path));

            foreach ($connections as $name) {
                $command = $credential->connection($name);
                $this->line(sprintf('  %s: %s', $name, $command));
            }

            return self::SUCCESS;
        }

        // Get specific connection
        $command = $credential->connection($connectionName);

        if ($command === null) {
            $this->error(sprintf("Connection '%s' not found for: %s", $connectionName, $path));
            $this->newLine();
            $this->info('Available connections: '.implode(', ', $connections));

            return self::FAILURE;
        }

        // Copy to clipboard
        if ($this->option('copy')) {
            $this->copyToClipboard($command);
            $this->info('Command copied to clipboard: '.$command);

            return self::SUCCESS;
        }

        // Execute the command
        $this->info('Executing: '.$command);
        $this->newLine();

        $process = Process::fromShellCommandline($command);
        $process->setTty(true);
        $process->setTimeout(null);

        try {
            $process->run(function ($type, string|iterable $buffer): void {
                $this->output->write($buffer);
            });

            return $process->getExitCode() ?? self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Failed to execute command: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Copy text to the system clipboard.
     *
     * Uses platform-specific clipboard commands (pbcopy on macOS, xclip on Linux,
     * clip on Windows) to copy the connection command to the system clipboard.
     * Fails silently if the clipboard command is not available in the environment.
     *
     * @param string $text The text to copy to clipboard
     */
    private function copyToClipboard(string $text): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => 'pbcopy',
            'Linux' => 'xclip -selection clipboard',
            'Windows' => 'clip',
            default => null,
        };

        if ($command === null) {
            $this->warn('Clipboard not supported on this platform');

            return;
        }

        try {
            $process = Process::fromShellCommandline($command);
            $process->setInput($text);
            $process->run();
        } catch (Throwable) {
            // Clipboard command may not be available in all environments (e.g., Docker)
        }
    }
}
