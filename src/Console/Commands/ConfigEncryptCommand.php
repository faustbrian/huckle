<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\Exceptions\DirectoryNotFoundException;
use Cline\Huckle\Exceptions\EncryptionFailedException;
use Cline\Huckle\Exceptions\FileNotFoundException;
use Cline\Huckle\Exceptions\MissingAppKeyException;
use Cline\Huckle\HuckleManager;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;

use function base64_encode;
use function config;
use function count;
use function is_dir;
use function mb_substr;
use function sprintf;
use function str_starts_with;

/**
 * Artisan command to encrypt configuration files.
 *
 * Provides CLI interface for encrypting sensitive HCL configuration files
 * using Laravel's Encrypter. Supports custom keys, ciphers, environment-specific
 * files, and automatic cleanup of original files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigEncryptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:encrypt
        {file : The configuration file or directory to encrypt}
        {--key= : The encryption key (generates one if not provided)}
        {--app-key : Use the application APP_KEY for encryption}
        {--cipher= : The encryption cipher (default: AES-256-CBC)}
        {--env= : The environment suffix or directory}
        {--env-style= : Environment file style: suffix or directory}
        {--prune : Delete the original file(s) after encryption}
        {--force : Overwrite existing encrypted file(s)}
        {--recursive : Process subdirectories recursively (directories only)}
        {--glob= : Glob pattern to filter files (directories only, e.g., *.hcl)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt a configuration file or directory';

    /**
     * Execute the console command.
     *
     * Encrypts a configuration file or directory using Laravel's Encrypter.
     * Determines whether to process a single file or directory and delegates
     * to the appropriate handler method. Generates a new encryption key if
     * none is provided.
     *
     * @param HuckleManager $manager The Huckle manager instance for handling encryption
     *
     * @throws DirectoryNotFoundException If the directory does not exist
     * @throws EncryptionFailedException  If encryption fails or data is invalid
     * @throws FileNotFoundException      If the source file does not exist
     * @throws MissingAppKeyException     If --app-key is used but APP_KEY is not set
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

        /** @var null|string $cipher */
        $cipher = $this->option('cipher');

        /** @var null|string $env */
        $env = $this->option('env');

        /** @var null|string $envStyle */
        $envStyle = $this->option('env-style');

        /** @var bool $prune */
        $prune = (bool) $this->option('prune');

        /** @var bool $force */
        $force = (bool) $this->option('force');

        /** @var bool $recursive */
        $recursive = (bool) $this->option('recursive');

        /** @var null|string $glob */
        $glob = $this->option('glob');

        // Resolve encryption key
        try {
            $key = $this->resolveEncryptionKey($key, $useAppKey, $cipher);
        } catch (MissingAppKeyException $missingAppKeyException) {
            $this->components->error($missingAppKeyException->getMessage());

            return self::FAILURE;
        }

        // Handle directory encryption
        if (is_dir($file)) {
            return $this->handleDirectory($manager, $file, $key, $cipher, $prune, $force, $recursive, $glob);
        }

        // Handle single file encryption
        return $this->handleFile($manager, $file, $key, $cipher, $prune, $force, $env, $envStyle);
    }

    /**
     * Handle encryption of a single file.
     *
     * Encrypts a single configuration file and provides feedback about the
     * operation result, including the encryption key for future decryption.
     * Optionally removes the original file after successful encryption.
     *
     * @param HuckleManager $manager  The Huckle manager instance
     * @param string        $file     Path to the file to encrypt
     * @param string        $key      The encryption key (base64-encoded)
     * @param null|string   $cipher   The cipher algorithm (e.g., AES-256-CBC)
     * @param bool          $prune    Whether to delete the original file after encryption
     * @param bool          $force    Whether to overwrite existing encrypted files
     * @param null|string   $env      Environment suffix or directory name
     * @param null|string   $envStyle Environment file style (suffix or directory)
     *
     * @throws EncryptionFailedException If encryption fails
     * @throws FileNotFoundException     If the file does not exist
     *
     * @return int Command exit status (SUCCESS or FAILURE)
     */
    private function handleFile(
        HuckleManager $manager,
        string $file,
        string $key,
        ?string $cipher,
        bool $prune,
        bool $force,
        ?string $env,
        ?string $envStyle,
    ): int {
        try {
            $result = $manager->encrypt(
                filepath: $file,
                key: $key,
                cipher: $cipher,
                prune: $prune,
                force: $force,
                env: $env,
                envStyle: $envStyle,
            );

            $this->components->info('Configuration file encrypted successfully.');
            $this->components->twoColumnDetail('Encrypted file', $result['path']);
            $this->components->twoColumnDetail('Cipher', $cipher ?? 'AES-256-CBC');

            $this->newLine();
            $this->components->warn('Store this key securely. You will need it to decrypt the file:');
            $this->newLine();
            $this->line('  php artisan huckle:decrypt '.$result['path'].' --key="'.$result['key'].'"');

            if ($prune) {
                $this->newLine();
                $this->components->info('Original file has been deleted.');
            }

            return self::SUCCESS;
        } catch (FileNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (EncryptionFailedException $e) {
            $this->components->error('Encryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Handle encryption of a directory.
     *
     * Encrypts all configuration files within a directory and provides feedback
     * about the batch operation results. Supports recursive directory traversal
     * and glob pattern filtering.
     *
     * @param HuckleManager $manager   The Huckle manager instance
     * @param string        $directory Path to the directory containing files to encrypt
     * @param string        $key       The encryption key (base64-encoded)
     * @param null|string   $cipher    The cipher algorithm (e.g., AES-256-CBC)
     * @param bool          $prune     Whether to delete the original files after encryption
     * @param bool          $force     Whether to overwrite existing encrypted files
     * @param bool          $recursive Whether to process subdirectories recursively
     * @param null|string   $glob      Optional glob pattern to filter files (e.g., *.hcl)
     *
     * @throws DirectoryNotFoundException If the directory does not exist
     * @throws EncryptionFailedException  If encryption fails for any file
     *
     * @return int Command exit status (SUCCESS or FAILURE)
     */
    private function handleDirectory(
        HuckleManager $manager,
        string $directory,
        string $key,
        ?string $cipher,
        bool $prune,
        bool $force,
        bool $recursive,
        ?string $glob,
    ): int {
        try {
            $result = $manager->encryptDirectory(
                directory: $directory,
                key: $key,
                cipher: $cipher,
                prune: $prune,
                force: $force,
                recursive: $recursive,
                glob: $glob,
            );

            $fileCount = count($result['files']);

            if ($fileCount === 0) {
                $this->components->warn('No files found to encrypt in directory.');

                return self::SUCCESS;
            }

            $this->components->info(sprintf('Directory encrypted successfully. %d file(s) encrypted.', $fileCount));
            $this->components->twoColumnDetail('Directory', $directory);
            $this->components->twoColumnDetail('Cipher', $cipher ?? 'AES-256-CBC');
            $this->components->twoColumnDetail('Recursive', $recursive ? 'Yes' : 'No');

            if ($glob !== null) {
                $this->components->twoColumnDetail('Pattern', $glob);
            }

            $this->newLine();
            $this->components->twoColumnDetail('Encrypted files', '');

            foreach ($result['files'] as $fileResult) {
                $this->line('  • '.$fileResult['path']);
            }

            $this->newLine();
            $this->components->warn('Store this key securely. You will need it to decrypt the files:');
            $this->newLine();
            $this->line('  php artisan huckle:decrypt '.$directory.' --key="'.$result['key'].'"'.($recursive ? ' --recursive' : ''));

            if ($prune) {
                $this->newLine();
                $this->components->info('Original files have been deleted.');
            }

            return self::SUCCESS;
        } catch (DirectoryNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (EncryptionFailedException $e) {
            $this->components->error('Encryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Resolve the encryption key from options, config, or generate a new one.
     *
     * Determines the encryption key to use based on command options and
     * configuration settings. If no key is provided, generates a new one
     * using Laravel's Encrypter. Handles APP_KEY extraction and base64
     * prefix stripping when using the application key.
     *
     * @param null|string $key       The explicit key provided via --key option
     * @param bool        $useAppKey Whether to use the application APP_KEY
     * @param null|string $cipher    The cipher algorithm for key generation
     *
     * @throws MissingAppKeyException If --app-key is used but APP_KEY is not set
     *
     * @return string The resolved or generated encryption key
     */
    private function resolveEncryptionKey(?string $key, bool $useAppKey, ?string $cipher): string
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
                throw MissingAppKeyException::forEncryption();
            }

            // Strip 'base64:' prefix if present and return raw base64
            if (str_starts_with($appKey, 'base64:')) {
                return mb_substr($appKey, 7);
            }

            return $appKey;
        }

        // Generate a new key
        $resolvedCipher = $cipher ?? 'AES-256-CBC';

        return base64_encode(Encrypter::generateKey($resolvedCipher));
    }
}
