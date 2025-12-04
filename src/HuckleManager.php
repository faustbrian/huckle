<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle;

use Cline\Huckle\Exceptions\DecryptionFailedException;
use Cline\Huckle\Exceptions\DirectoryNotFoundException;
use Cline\Huckle\Exceptions\EncryptionFailedException;
use Cline\Huckle\Exceptions\FileNotFoundException;
use Cline\Huckle\Exceptions\InvalidBase64KeyException;
use Cline\Huckle\Exceptions\ReadOnlyConfigurationException;
use Cline\Huckle\Parser\Credential;
use Cline\Huckle\Parser\Group;
use Cline\Huckle\Parser\HuckleConfig;
use Cline\Huckle\Parser\HuckleParser;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Throwable;

use const DIRECTORY_SEPARATOR;
use const GLOB_NOSORT;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

use function array_merge;
use function array_unique;
use function assert;
use function base64_decode;
use function base64_encode;
use function basename;
use function config;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function mb_rtrim;
use function pathinfo;
use function preg_match;
use function putenv;
use function sprintf;
use function str_ends_with;
use function throw_if;
use function unlink;

/**
 * Main manager for Huckle credential operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HuckleManager
{
    private ?HuckleConfig $config = null;

    private readonly HuckleParser $parser;

    /**
     * Create a new Huckle manager.
     *
     * @param Application $app The Laravel application instance
     */
    public function __construct(
        private readonly Application $app,
    ) {
        $this->parser = new HuckleParser();
    }

    /**
     * Load and parse the Huckle configuration.
     *
     * @param  null|string  $path Optional path override
     * @return HuckleConfig The parsed configuration
     */
    public function load(?string $path = null): HuckleConfig
    {
        $path ??= $this->getConfigPath();

        $this->config = $this->parser->parseFile($path);

        return $this->config;
    }

    /**
     * Load HCL file and export matching division values to the environment.
     *
     * @param  string                $path    The path to the HCL file
     * @param  array<string, string> $context The context variables (e.g., ['division' => 'FI'])
     * @return array<string, string> The exported variables
     */
    public function loadEnv(string $path, array $context): array
    {
        $this->config = $this->parser->parseFile($path);

        $exports = $this->config->exportsForContext($context);

        foreach ($exports as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return $exports;
    }

    /**
     * Get the current configuration, loading it if necessary.
     *
     * @return HuckleConfig The configuration
     */
    public function config(): HuckleConfig
    {
        if (!$this->config instanceof HuckleConfig) {
            $this->load();
        }

        assert($this->config instanceof HuckleConfig);

        return $this->config;
    }

    /**
     * Get a credential by path.
     *
     * @param  string          $path The credential path (e.g., "database.production.main")
     * @return null|Credential The credential or null
     */
    public function get(string $path): ?Credential
    {
        return $this->config()->get($path);
    }

    /**
     * Check if a credential exists.
     *
     * @param  string $path The credential path
     * @return bool   True if the credential exists
     */
    public function has(string $path): bool
    {
        return $this->config()->has($path);
    }

    /**
     * Get a group by path.
     *
     * @param  string     $path The group path (e.g., "database.production")
     * @return null|Group The group or null
     */
    public function group(string $path): ?Group
    {
        return $this->config()->group($path);
    }

    /**
     * Get all groups.
     *
     * @return Collection<string, Group> The groups
     */
    public function groups(): Collection
    {
        return $this->config()->groups();
    }

    /**
     * Get all credentials.
     *
     * @return Collection<string, Credential> The credentials
     */
    public function credentials(): Collection
    {
        return $this->config()->credentials();
    }

    /**
     * Get credentials filtered by tag.
     *
     * @param  string                         ...$tags Tags to filter by
     * @return Collection<string, Credential> Filtered credentials
     */
    public function tagged(string ...$tags): Collection
    {
        return $this->config()->tagged(...$tags);
    }

    /**
     * Get credentials in a specific environment.
     *
     * @param  string                         $environment The environment name
     * @return Collection<string, Credential> Filtered credentials
     */
    public function inEnvironment(string $environment): Collection
    {
        return $this->config()->inEnvironment($environment);
    }

    /**
     * Get credentials in a specific group.
     *
     * @param  string                         $group       The group name
     * @param  null|string                    $environment Optional environment filter
     * @return Collection<string, Credential> Filtered credentials
     */
    public function inGroup(string $group, ?string $environment = null): Collection
    {
        return $this->config()->inGroup($group, $environment);
    }

    /**
     * Get exported environment variables for a credential.
     *
     * @param  string                $path The credential path
     * @return array<string, string> The exports
     */
    public function exports(string $path): array
    {
        return $this->config()->exports($path);
    }

    /**
     * Get all exported environment variables.
     *
     * @return array<string, string> Combined exports
     */
    public function allExports(): array
    {
        return $this->config()->allExports();
    }

    /**
     * Export a credential's values to the environment.
     *
     * @param string $path The credential path
     */
    public function exportToEnv(string $path): self
    {
        $exports = $this->exports($path);

        foreach ($exports as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return $this;
    }

    /**
     * Export all credential values to the environment.
     */
    public function exportAllToEnv(): self
    {
        $exports = $this->allExports();

        foreach ($exports as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return $this;
    }

    /**
     * Get a connection command for a credential.
     *
     * @param  string      $path           The credential path
     * @param  string      $connectionName The connection name
     * @return null|string The connection command or null
     */
    public function connection(string $path, string $connectionName): ?string
    {
        $credential = $this->get($path);

        return $credential?->connection($connectionName);
    }

    /**
     * Get credentials that are expiring soon.
     *
     * @param  null|int                       $days Days to consider "soon" (defaults to config)
     * @return Collection<string, Credential> Expiring credentials
     */
    public function expiring(?int $days = null): Collection
    {
        /** @var int $defaultDays */
        $defaultDays = Config::get('huckle.expiry_warning', 30);
        $days ??= $defaultDays;

        return $this->config()->expiring($days);
    }

    /**
     * Get credentials that are expired.
     *
     * @return Collection<string, Credential> Expired credentials
     */
    public function expired(): Collection
    {
        return $this->config()->expired();
    }

    /**
     * Get credentials that need rotation.
     *
     * @param  null|int                       $days Max days since rotation (defaults to config)
     * @return Collection<string, Credential> Credentials needing rotation
     */
    public function needsRotation(?int $days = null): Collection
    {
        /** @var int $defaultDays */
        $defaultDays = Config::get('huckle.rotation_warning', 90);
        $days ??= $defaultDays;

        return $this->config()->needsRotation($days);
    }

    /**
     * Validate the HCL configuration.
     *
     * @param  null|string          $path Optional path override
     * @return array<string, mixed> Validation result
     */
    public function validate(?string $path = null): array
    {
        $path ??= $this->getConfigPath();

        return $this->parser->validateFile($path);
    }

    /**
     * Flush the cached configuration.
     */
    public function flush(): self
    {
        $this->config = null;

        return $this;
    }

    /**
     * Get the path to the Huckle config file.
     *
     * @return string The config path
     */
    public function getConfigPath(): string
    {
        // Check for environment-specific config
        $environment = $this->app->environment();

        /** @var array<string, string> $environments */
        $environments = Config::get('huckle.environments', []);

        if (isset($environments[$environment]) && file_exists($environments[$environment])) {
            return $environments[$environment];
        }

        /** @var string */
        return Config::get('huckle.path', $this->app->basePath('config/huckle.hcl'));
    }

    /**
     * Compare credentials between two environments.
     *
     * @param  string               $env1 First environment
     * @param  string               $env2 Second environment
     * @return array<string, mixed> Comparison result
     */
    public function diff(string $env1, string $env2): array
    {
        $creds1 = $this->inEnvironment($env1)->keyBy(fn (Credential $c): string => sprintf('%s.%s', $c->group, $c->name));
        $creds2 = $this->inEnvironment($env2)->keyBy(fn (Credential $c): string => sprintf('%s.%s', $c->group, $c->name));

        $only1 = $creds1->diffKeys($creds2)->keys()->all();
        $only2 = $creds2->diffKeys($creds1)->keys()->all();
        $both = $creds1->intersectByKeys($creds2)->keys()->all();

        $differences = [];

        foreach ($both as $key) {
            $c1 = $creds1->get($key);
            $c2 = $creds2->get($key);

            if ($c1 === null) {
                continue;
            }

            if ($c2 === null) {
                continue;
            }

            $fieldDiff = $this->compareCredentials($c1, $c2);

            if ($fieldDiff === []) {
                continue;
            }

            $differences[$key] = $fieldDiff;
        }

        return [
            'only_in_'.$env1 => $only1,
            'only_in_'.$env2 => $only2,
            'differences' => $differences,
        ];
    }

    /**
     * Encrypt a configuration file.
     *
     * Uses Laravel's Encrypter (AES-256-CBC). The encrypted file will have '.encrypted' appended.
     * Key format: 'base64:...' or raw base64 string.
     *
     * @param string      $filepath Path to the configuration file (or base path when using env)
     * @param null|string $key      Encryption key (generates one if null)
     * @param null|string $cipher   Cipher algorithm (default from config or AES-256-CBC)
     * @param bool        $prune    Delete the original file after encryption (default: false)
     * @param bool        $force    Overwrite existing encrypted file (default: false)
     * @param null|string $env      Environment name (e.g., 'production')
     * @param null|string $envStyle Environment style: 'suffix' (config.production.hcl) or 'directory' (production/config.hcl)
     *
     * @throws EncryptionFailedException If encryption fails or encrypted file exists and force is false
     * @throws FileNotFoundException     If file doesn't exist
     *
     * @return array{path: string, key: string} The encrypted file path and key (raw base64)
     */
    public function encrypt(
        string $filepath,
        ?string $key = null,
        ?string $cipher = null,
        bool $prune = false,
        bool $force = false,
        ?string $env = null,
        ?string $envStyle = null,
    ): array {
        /** @var string $resolvedCipher */
        $resolvedCipher = $cipher ?? $this->getEncryptionConfig('cipher', 'AES-256-CBC');

        // Handle environment-specific file paths
        $sourcePath = $this->resolveEnvFilePath($filepath, $env, $envStyle);

        if (!file_exists($sourcePath)) {
            throw FileNotFoundException::forPath($sourcePath);
        }

        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw ReadOnlyConfigurationException::forPath($sourcePath);
        }

        // Generate key if not provided
        $originalKey = $key;

        $key = $key !== null ? $this->parseEncryptionKey($key) : Encrypter::generateKey($resolvedCipher);

        try {
            $encrypter = new Encrypter($key, $resolvedCipher);
            $encryptedData = $encrypter->encrypt($contents);
        } catch (Throwable $throwable) {
            throw EncryptionFailedException::forPath($sourcePath, $throwable->getMessage());
        }

        $encryptedPath = $sourcePath.'.encrypted';

        if (!$force && file_exists($encryptedPath)) {
            throw EncryptionFailedException::forPath($sourcePath, 'Encrypted file already exists. Use force=true to overwrite.');
        }

        file_put_contents($encryptedPath, $encryptedData);

        if ($prune) {
            unlink($sourcePath);
        }

        return [
            'path' => $encryptedPath,
            'key' => $originalKey ?? base64_encode($key),
        ];
    }

    /**
     * Decrypt an encrypted configuration file.
     *
     * @param string      $encryptedPath Path to the encrypted file (or base path when using env)
     * @param string      $key           The decryption key (base64: prefixed or raw)
     * @param bool        $force         Overwrite existing decrypted file (default: false)
     * @param null|string $cipher        Cipher algorithm (default from config or AES-256-CBC)
     * @param null|string $path          Custom output directory path
     * @param null|string $filename      Custom output filename
     * @param null|string $env           Environment name (e.g., 'production')
     * @param bool        $prune         Delete the encrypted file after decryption (default: false)
     * @param null|string $envStyle      Environment style: 'suffix' or 'directory'
     *
     * @throws DecryptionFailedException If decryption fails or file exists and force is false
     * @throws FileNotFoundException     If encrypted file doesn't exist
     *
     * @return string Path to the decrypted file
     */
    public function decrypt(
        string $encryptedPath,
        string $key,
        bool $force = false,
        ?string $cipher = null,
        ?string $path = null,
        ?string $filename = null,
        ?string $env = null,
        bool $prune = false,
        ?string $envStyle = null,
    ): string {
        /** @var string $resolvedCipher */
        $resolvedCipher = $cipher ?? $this->getEncryptionConfig('cipher', 'AES-256-CBC');

        // Handle environment-specific file paths
        $sourcePath = $env !== null
            ? $this->resolveEnvFilePath($encryptedPath, $env, $envStyle).'.encrypted'
            : $encryptedPath;

        if (!file_exists($sourcePath)) {
            throw FileNotFoundException::forPath($sourcePath);
        }

        // Determine output path
        $decryptedPath = $this->resolveDecryptedPath($sourcePath, $path, $filename);

        if (!$force && file_exists($decryptedPath)) {
            throw DecryptionFailedException::forPath($sourcePath, 'Decrypted file already exists. Use force=true to overwrite.');
        }

        $encryptedContents = file_get_contents($sourcePath);

        if ($encryptedContents === false) {
            throw ReadOnlyConfigurationException::forPath($sourcePath);
        }

        try {
            $parsedKey = $this->parseEncryptionKey($key);
            $encrypter = new Encrypter($parsedKey, $resolvedCipher);
            $decrypted = $encrypter->decrypt($encryptedContents);
        } catch (Throwable $throwable) {
            throw DecryptionFailedException::forPath($sourcePath, $throwable->getMessage());
        }

        // Ensure output directory exists
        $outputDir = dirname($decryptedPath);

        if (!is_dir($outputDir)) {
            throw DirectoryNotFoundException::forPath($outputDir);
        }

        file_put_contents($decryptedPath, $decrypted);

        if ($prune) {
            unlink($sourcePath);
        }

        return $decryptedPath;
    }

    /**
     * Encrypt all files in a directory.
     *
     * Uses the same encryption key for all files. Optionally processes subdirectories recursively.
     *
     * @param string      $directory Path to the directory to encrypt
     * @param null|string $key       Encryption key (generates one if null)
     * @param null|string $cipher    Cipher algorithm (default from config or AES-256-CBC)
     * @param bool        $prune     Delete original files after encryption (default: false)
     * @param bool        $force     Overwrite existing encrypted files (default: false)
     * @param bool        $recursive Process subdirectories recursively (default: false)
     * @param null|string $glob      Glob pattern to filter files (e.g., '*.hcl')
     *
     * @throws DirectoryNotFoundException If directory doesn't exist
     *
     * @return array{files: array<array{path: string, key: string}>, key: string} Encrypted file results and the key used
     */
    public function encryptDirectory(
        string $directory,
        ?string $key = null,
        ?string $cipher = null,
        bool $prune = false,
        bool $force = false,
        bool $recursive = false,
        ?string $glob = null,
    ): array {
        if (!is_dir($directory)) {
            throw DirectoryNotFoundException::forPath($directory);
        }

        /** @var string $resolvedCipher */
        $resolvedCipher = $cipher ?? $this->getEncryptionConfig('cipher', 'AES-256-CBC');

        // Generate key once for all files if not provided
        $originalKey = $key;
        $key ??= base64_encode(Encrypter::generateKey($resolvedCipher));

        $files = $this->collectFilesForEncryption($directory, $recursive, $glob);
        $results = [];

        foreach ($files as $file) {
            $results[] = $this->encrypt(
                filepath: $file,
                key: $key,
                cipher: $cipher,
                prune: $prune,
                force: $force,
            );
        }

        return [
            'files' => $results,
            'key' => $originalKey ?? $key,
        ];
    }

    /**
     * Decrypt all encrypted files in a directory.
     *
     * Processes all files ending with '.encrypted' in the specified directory.
     *
     * @param string      $directory Path to the directory containing encrypted files
     * @param string      $key       The decryption key (base64: prefixed or raw)
     * @param bool        $force     Overwrite existing decrypted files (default: false)
     * @param null|string $cipher    Cipher algorithm (default from config or AES-256-CBC)
     * @param bool        $prune     Delete encrypted files after decryption (default: false)
     * @param bool        $recursive Process subdirectories recursively (default: false)
     *
     * @throws DirectoryNotFoundException If directory doesn't exist
     *
     * @return array<string> Paths to the decrypted files
     */
    public function decryptDirectory(
        string $directory,
        string $key,
        bool $force = false,
        ?string $cipher = null,
        bool $prune = false,
        bool $recursive = false,
    ): array {
        if (!is_dir($directory)) {
            throw DirectoryNotFoundException::forPath($directory);
        }

        $files = $this->collectFilesForDecryption($directory, $recursive);
        $results = [];

        foreach ($files as $file) {
            $results[] = $this->decrypt(
                encryptedPath: $file,
                key: $key,
                force: $force,
                cipher: $cipher,
                prune: $prune,
            );
        }

        return $results;
    }

    /**
     * Compare two credentials field by field.
     *
     * @param  Credential                          $c1 First credential
     * @param  Credential                          $c2 Second credential
     * @return array<string, array<string, mixed>> Field differences
     */
    private function compareCredentials(Credential $c1, Credential $c2): array
    {
        $diff = [];
        $allFields = array_merge($c1->fieldNames(), $c2->fieldNames());
        $allFields = array_unique($allFields);

        foreach ($allFields as $field) {
            $v1 = $c1->get($field);
            $v2 = $c2->get($field);

            if ($v1 === $v2) {
                continue;
            }

            $diff[$field] = [
                $c1->environment => $v1,
                $c2->environment => $v2,
            ];
        }

        return $diff;
    }

    /**
     * Collect files for encryption from a directory.
     *
     * @param  string        $directory The directory to scan
     * @param  bool          $recursive Whether to scan subdirectories
     * @param  null|string   $glob      Glob pattern to filter files
     * @return array<string> List of file paths
     */
    private function collectFilesForEncryption(string $directory, bool $recursive, ?string $glob): array
    {
        $directory = mb_rtrim($directory, DIRECTORY_SEPARATOR);
        $pattern = $glob ?? '*';
        $files = [];

        // Get files matching pattern in current directory
        $matches = glob($directory.DIRECTORY_SEPARATOR.$pattern, GLOB_NOSORT);

        if ($matches !== false) {
            foreach ($matches as $match) {
                // Skip already encrypted files and directories
                if (!is_file($match)) {
                    continue;
                }

                if (str_ends_with($match, '.encrypted')) {
                    continue;
                }

                $files[] = $match;
            }
        }

        // Recursively process subdirectories if requested
        if ($recursive) {
            $subdirs = glob($directory.DIRECTORY_SEPARATOR.'*', GLOB_NOSORT);

            if ($subdirs !== false) {
                foreach ($subdirs as $subdir) {
                    if (!is_dir($subdir)) {
                        continue;
                    }

                    $files = array_merge($files, $this->collectFilesForEncryption($subdir, true, $glob));
                }
            }
        }

        return $files;
    }

    /**
     * Collect encrypted files for decryption from a directory.
     *
     * @param  string        $directory The directory to scan
     * @param  bool          $recursive Whether to scan subdirectories
     * @return array<string> List of encrypted file paths
     */
    private function collectFilesForDecryption(string $directory, bool $recursive): array
    {
        $directory = mb_rtrim($directory, DIRECTORY_SEPARATOR);
        $files = [];

        // Get all .encrypted files in current directory
        $matches = glob($directory.DIRECTORY_SEPARATOR.'*.encrypted', GLOB_NOSORT);

        if ($matches !== false) {
            foreach ($matches as $match) {
                if (!is_file($match)) {
                    continue;
                }

                $files[] = $match;
            }
        }

        // Recursively process subdirectories if requested
        if ($recursive) {
            $subdirs = glob($directory.DIRECTORY_SEPARATOR.'*', GLOB_NOSORT);

            if ($subdirs !== false) {
                foreach ($subdirs as $subdir) {
                    if (!is_dir($subdir)) {
                        continue;
                    }

                    $files = array_merge($files, $this->collectFilesForDecryption($subdir, true));
                }
            }
        }

        return $files;
    }

    /**
     * Parse the encryption key (base64 encoded).
     *
     * @param  string $key The encryption key (base64 encoded)
     * @return string The decoded binary key
     */
    private function parseEncryptionKey(string $key): string
    {
        $decoded = base64_decode($key, true);

        throw_if($decoded === false, InvalidBase64KeyException::invalidEncoding());

        return $decoded;
    }

    /**
     * Resolve environment-specific file path.
     *
     * Supports two styles:
     * - 'suffix': config.hcl + env 'production' -> config.production.hcl
     * - 'directory': config.hcl + env 'production' -> production/config.hcl
     *
     * @param  string      $filepath Base file path
     * @param  null|string $env      Environment name (e.g., 'production', 'staging')
     * @param  null|string $envStyle Environment style: 'suffix' or 'directory' (default from config)
     * @return string      Resolved file path
     */
    private function resolveEnvFilePath(string $filepath, ?string $env, ?string $envStyle = null): string
    {
        if ($env === null) {
            return $filepath;
        }

        $envStyle ??= $this->getEncryptionConfig('env_style', 'suffix');
        $directory = dirname($filepath);
        $filename = basename($filepath);

        if ($envStyle === 'directory') {
            /** @var null|string $envDirectory */
            $envDirectory = $this->getEncryptionConfig('env_directory');

            if ($envDirectory !== null) {
                // Use configured env_directory as base: config/app.hcl -> config/{env_directory}/production/app.hcl
                return $directory.DIRECTORY_SEPARATOR.$envDirectory.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.$filename;
            }

            // Default: config/app.hcl -> config/production/app.hcl
            return $directory.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.$filename;
        }

        // Suffix style: config.hcl -> config.production.hcl
        $baseFilename = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        return $directory.DIRECTORY_SEPARATOR.$baseFilename.'.'.$env.'.'.$extension;
    }

    /**
     * Get encryption configuration value.
     *
     * @param  string $key     Configuration key within encryption array
     * @param  mixed  $default Default value if not configured
     * @return mixed  Configuration value
     */
    private function getEncryptionConfig(string $key, mixed $default = null): mixed
    {
        return config('huckle.encryption.'.$key, $default);
    }

    /**
     * Resolve the decrypted file output path.
     *
     * @param  string      $encryptedPath Original encrypted file path
     * @param  null|string $path          Custom output directory
     * @param  null|string $filename      Custom output filename
     * @return string      Resolved output path
     */
    private function resolveDecryptedPath(string $encryptedPath, ?string $path, ?string $filename): string
    {
        // Determine base filename (remove .encrypted suffix)
        $baseFilename = preg_match('/\.encrypted$/', $encryptedPath)
            ? basename($encryptedPath, '.encrypted')
            : basename($encryptedPath).'.decrypted';

        // Use custom filename if provided
        $outputFilename = $filename ?? $baseFilename;

        // Use custom path or original directory
        $outputDir = $path ?? dirname($encryptedPath);

        return mb_rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$outputFilename;
    }
}
