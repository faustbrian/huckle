<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\Exceptions\DecryptionFailedException;
use Cline\Huckle\Exceptions\DirectoryNotFoundException;
use Cline\Huckle\Exceptions\FileNotFoundException;
use Cline\Huckle\HuckleManager;
use Illuminate\Console\Command;

use function config;
use function count;
use function is_dir;
use function mb_substr;
use function sprintf;
use function str_starts_with;

/**
 * Artisan command to decrypt configuration files.
 *
 * Provides CLI interface for decrypting encrypted HCL configuration files
 * using Laravel's Encrypter. Supports custom keys, ciphers, environment-specific
 * files, custom output paths, and automatic cleanup of encrypted files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigDecryptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:decrypt
        {file : The encrypted configuration file or directory to decrypt}
        {--key= : The encryption key}
        {--app-key : Use the application APP_KEY for decryption}
        {--cipher= : The encryption cipher (default: AES-256-CBC)}
        {--env= : The environment suffix or directory}
        {--env-style= : Environment file style: suffix or directory}
        {--path= : Custom output directory}
        {--filename= : Custom output filename}
        {--keep : Keep the encrypted file(s) after decryption}
        {--force : Overwrite existing decrypted file(s)}
        {--recursive : Process subdirectories recursively (directories only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decrypt an encrypted configuration file or directory';

    /**
     * Execute the console command.
     *
     * Decrypts an encrypted configuration file or directory using Laravel's
     * Encrypter. Determines whether to process a single file or directory
     * and delegates to the appropriate handler method.
     *
     * @param HuckleManager $manager The Huckle manager instance for handling decryption
     *
     * @throws DecryptionFailedException  If decryption fails or data is invalid
     * @throws DirectoryNotFoundException If the directory does not exist
     * @throws FileNotFoundException      If the source file does not exist
     *
     * @return int Command exit status (SUCCESS or FAILURE)
     */
    public function handle(HuckleManager $manager): int
    {
        /** @var string $file */
        $file = $this->argument('file');

        /** @var null|string $key */
        $key = $this->option('key');

        /** @var bool $useAppKey */
        $useAppKey = (bool) $this->option('app-key');

        // Resolve encryption key
        $resolvedKey = $this->resolveDecryptionKey($key, $useAppKey);

        if ($resolvedKey === null) {
            $this->components->error('No decryption key provided. Use --key or --app-key option, or set huckle.encryption.use_app_key to true.');

            return self::FAILURE;
        }

        /** @var null|string $cipher */
        $cipher = $this->option('cipher');

        /** @var null|string $env */
        $env = $this->option('env');

        /** @var null|string $envStyle */
        $envStyle = $this->option('env-style');

        /** @var null|string $path */
        $path = $this->option('path');

        /** @var null|string $filename */
        $filename = $this->option('filename');

        /** @var bool $keep */
        $keep = (bool) $this->option('keep');

        /** @var bool $force */
        $force = (bool) $this->option('force');

        /** @var bool $recursive */
        $recursive = (bool) $this->option('recursive');

        // Handle directory decryption
        if (is_dir($file)) {
            return $this->handleDirectory($manager, $file, $resolvedKey, $cipher, $keep, $force, $recursive);
        }

        // Handle single file decryption
        return $this->handleFile($manager, $file, $resolvedKey, $cipher, $keep, $force, $path, $filename, $env, $envStyle);
    }

    /**
     * Handle decryption of a single file.
     *
     * Decrypts a single encrypted configuration file and provides feedback
     * about the operation result. Optionally removes the encrypted file
     * after successful decryption.
     *
     * @param HuckleManager $manager  The Huckle manager instance
     * @param string        $file     Path to the encrypted file to decrypt
     * @param string        $key      The decryption key (base64-encoded)
     * @param null|string   $cipher   The cipher algorithm (e.g., AES-256-CBC)
     * @param bool          $keep     Whether to keep the encrypted file after decryption
     * @param bool          $force    Whether to overwrite existing decrypted files
     * @param null|string   $path     Custom output directory for the decrypted file
     * @param null|string   $filename Custom filename for the decrypted file
     * @param null|string   $env      Environment suffix or directory name
     * @param null|string   $envStyle Environment file style (suffix or directory)
     *
     * @throws DecryptionFailedException If decryption fails
     * @throws FileNotFoundException     If the file does not exist
     *
     * @return int Command exit status (SUCCESS or FAILURE)
     */
    private function handleFile(
        HuckleManager $manager,
        string $file,
        string $key,
        ?string $cipher,
        bool $keep,
        bool $force,
        ?string $path,
        ?string $filename,
        ?string $env,
        ?string $envStyle,
    ): int {
        try {
            $decryptedPath = $manager->decrypt(
                encryptedPath: $file,
                key: $key,
                force: $force,
                cipher: $cipher,
                path: $path,
                filename: $filename,
                env: $env,
                prune: !$keep,
                envStyle: $envStyle,
            );

            $this->components->info('Configuration file decrypted successfully.');
            $this->components->twoColumnDetail('Decrypted file', $decryptedPath);

            if ($keep) {
                $this->newLine();
                $this->components->warn('Encrypted file has been kept.');
            }

            return self::SUCCESS;
        } catch (FileNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (DecryptionFailedException $e) {
            $this->components->error('Decryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Handle decryption of a directory.
     *
     * Decrypts all encrypted configuration files within a directory and
     * provides feedback about the batch operation results. Supports
     * recursive directory traversal.
     *
     * @param HuckleManager $manager   The Huckle manager instance
     * @param string        $directory Path to the directory containing encrypted files
     * @param string        $key       The decryption key (base64-encoded)
     * @param null|string   $cipher    The cipher algorithm (e.g., AES-256-CBC)
     * @param bool          $keep      Whether to keep the encrypted files after decryption
     * @param bool          $force     Whether to overwrite existing decrypted files
     * @param bool          $recursive Whether to process subdirectories recursively
     *
     * @throws DecryptionFailedException  If decryption fails for any file
     * @throws DirectoryNotFoundException If the directory does not exist
     *
     * @return int Command exit status (SUCCESS or FAILURE)
     */
    private function handleDirectory(
        HuckleManager $manager,
        string $directory,
        string $key,
        ?string $cipher,
        bool $keep,
        bool $force,
        bool $recursive,
    ): int {
        try {
            $decryptedPaths = $manager->decryptDirectory(
                directory: $directory,
                key: $key,
                force: $force,
                cipher: $cipher,
                prune: !$keep,
                recursive: $recursive,
            );

            $fileCount = count($decryptedPaths);

            if ($fileCount === 0) {
                $this->components->warn('No encrypted files found in directory.');

                return self::SUCCESS;
            }

            $this->components->info(sprintf('Directory decrypted successfully. %d file(s) decrypted.', $fileCount));
            $this->components->twoColumnDetail('Directory', $directory);
            $this->components->twoColumnDetail('Recursive', $recursive ? 'Yes' : 'No');

            $this->newLine();
            $this->components->twoColumnDetail('Decrypted files', '');

            foreach ($decryptedPaths as $decryptedPath) {
                $this->line('  • '.$decryptedPath);
            }

            if ($keep) {
                $this->newLine();
                $this->components->warn('Encrypted files have been kept.');
            }

            return self::SUCCESS;
        } catch (DirectoryNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (DecryptionFailedException $e) {
            $this->components->error('Decryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Resolve the decryption key from options or config.
     *
     * Determines the decryption key to use based on command options and
     * configuration settings. Handles APP_KEY extraction and base64 prefix
     * stripping when using Laravel's application key.
     *
     * @param  null|string $key       The explicit key provided via --key option
     * @param  bool        $useAppKey Whether to use the application APP_KEY
     * @return null|string The resolved decryption key, or null if unavailable
     */
    private function resolveDecryptionKey(?string $key, bool $useAppKey): ?string
    {
        // Explicit --key option takes precedence
        if ($key !== null) {
            return $key;
        }

        // Check --app-key flag or config setting
        $shouldUseAppKey = $useAppKey || config('huckle.encryption.use_app_key', false);

        if ($shouldUseAppKey) {
            /** @var null|string $appKey */
            $appKey = config('app.key');

            if ($appKey === null) {
                $this->components->error('APP_KEY is not set. Cannot use --app-key without a configured application key.');

                return null;
            }

            // Strip 'base64:' prefix if present and return raw base64
            if (str_starts_with($appKey, 'base64:')) {
                return mb_substr($appKey, 7);
            }

            return $appKey;
        }

        // No key available
        return null;
    }
}
