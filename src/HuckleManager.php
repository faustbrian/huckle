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
use Cline\Huckle\Parser\HuckleConfig;
use Cline\Huckle\Parser\HuckleParser;
use Cline\Huckle\Parser\Node;
use Illuminate\Container\Attributes\Singleton;
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
use function array_values;
use function assert;
use function base64_decode;
use function base64_encode;
use function basename;
use function config;
use function count;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
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
 * Main manager for Huckle configuration operations.
 *
 * Provides comprehensive configuration management including HCL parsing, querying,
 * filtering, encryption/decryption, and environment variable exports. Uses a
 * unified Node model where all configuration blocks (partitions, environments,
 * providers, etc.) are represented consistently. Includes credential lifecycle
 * features such as expiration tracking and rotation warnings.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Singleton()]
final class HuckleManager
{
    /**
     * Cached parsed configuration instance.
     */
    private ?HuckleConfig $config = null;

    /**
     * HCL parser for reading and validating configuration files.
     */
    private readonly HuckleParser $parser;

    /**
     * Create a new Huckle manager.
     *
     * @param Application $app Laravel application instance providing access to configuration,
     *                         environment detection, and base path resolution
     */
    public function __construct(
        private readonly Application $app,
    ) {
        $this->parser = new HuckleParser();
    }

    /**
     * Load and parse the Huckle configuration.
     *
     * Parses the HCL configuration file and caches the result for subsequent calls.
     * Uses the configured path from huckle.path config or the provided override.
     *
     * @param null|string $path Optional absolute path override to load from a specific file
     *
     * @return HuckleConfig Parsed and validated configuration with all nodes
     */
    public function load(?string $path = null): HuckleConfig
    {
        $path ??= $this->getConfigPath();

        $this->config = $this->parser->parseFile($path);

        return $this->config;
    }

    /**
     * Load HCL file and export matching values to the environment.
     *
     * Parses the HCL file, filters nodes by context, and exports matching
     * environment variables to PHP's environment ($_ENV, $_SERVER, putenv).
     *
     * @param string                $path    Absolute path to the HCL configuration file
     * @param array<string, string> $context Context filter (e.g., ['partition' => 'FI', 'environment' => 'production'])
     *
     * @return array<string, string> Key-value map of exported environment variables
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
     * Get a node by path.
     *
     * @param  string    $path The node path (e.g., "FI.production.posti")
     * @return null|Node The node or null
     */
    public function get(string $path): ?Node
    {
        return $this->config()->get($path);
    }

    /**
     * Check if a node exists.
     *
     * @param  string $path The node path
     * @return bool   True if the node exists
     */
    public function has(string $path): bool
    {
        return $this->config()->has($path);
    }

    /**
     * Get all nodes.
     *
     * @return Collection<string, Node> The nodes
     */
    public function nodes(): Collection
    {
        return $this->config()->nodes();
    }

    /**
     * Get all partition nodes.
     *
     * @return Collection<string, Node> The partitions
     */
    public function partitions(): Collection
    {
        return $this->config()->partitions();
    }

    /**
     * Get nodes matching the given context.
     *
     * @param  array<string, string>    $context Context to match (partition, environment, provider, etc.)
     * @return Collection<string, Node> Matching nodes
     */
    public function matching(array $context): Collection
    {
        return $this->config()->matching($context);
    }

    /**
     * Get nodes filtered by tag.
     *
     * @param  string                   ...$tags Tags to filter by
     * @return Collection<string, Node> Filtered nodes
     */
    public function tagged(string ...$tags): Collection
    {
        return $this->config()->tagged(...$tags);
    }

    /**
     * Get exported environment variables for a node.
     *
     * @param  string                $path The node path
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
     * Get exports for a given context.
     *
     * @param  array<string, string> $context Context filter
     * @return array<string, string> Exports matching context
     */
    public function exportsForContext(array $context): array
    {
        return $this->config()->exportsForContext($context);
    }

    /**
     * Export a node's values to the environment.
     *
     * @param string $path Node path (e.g., "FI.production.posti")
     *
     * @return self Fluent interface for method chaining
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
     * Export context-matching values to the environment.
     *
     * @param  array<string, string> $context Context filter
     * @return self                  Fluent interface for method chaining
     */
    public function exportContextToEnv(array $context): self
    {
        $exports = $this->exportsForContext($context);

        foreach ($exports as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return $this;
    }

    /**
     * Export all values to the environment.
     *
     * @return self Fluent interface for method chaining
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
     * Get a connection command for a node.
     *
     * @param  string      $path           The node path
     * @param  string      $connectionName The connection name
     * @return null|string The connection command or null
     */
    public function connection(string $path, string $connectionName): ?string
    {
        $node = $this->get($path);

        return $node?->connection($connectionName);
    }

    /**
     * Get nodes that are expiring soon.
     *
     * @param  null|int                 $days Days to consider "soon" (defaults to config)
     * @return Collection<string, Node> Expiring nodes
     */
    public function expiring(?int $days = null): Collection
    {
        /** @var int $defaultDays */
        $defaultDays = Config::get('huckle.expiry_warning', 30);
        $days ??= $defaultDays;

        return $this->config()->expiring($days);
    }

    /**
     * Get nodes that are expired.
     *
     * @return Collection<string, Node> Expired nodes
     */
    public function expired(): Collection
    {
        return $this->config()->expired();
    }

    /**
     * Get nodes that need rotation.
     *
     * @param  null|int                 $days Max days since rotation (defaults to config)
     * @return Collection<string, Node> Nodes needing rotation
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
     *
     * @return self Fluent interface for method chaining
     */
    public function flush(): self
    {
        $this->config = null;

        return $this;
    }

    /**
     * Get the path to the Huckle config file.
     *
     * @return string Absolute path to the HCL configuration file
     */
    public function getConfigPath(): string
    {
        $environment = $this->app->environment();

        /** @var array<string, string> $environments */
        $environments = Config::get('huckle.environments', []);

        if (isset($environments[$environment]) && file_exists($environments[$environment])) {
            return $environments[$environment];
        }

        /** @var string */
        return Config::get('huckle.path', $this->app->basePath('.huckle'));
    }

    /**
     * Compare nodes between two environments.
     *
     * Compares service nodes across environments by stripping the environment
     * from paths to make comparable keys (e.g., database.production.main becomes database.main).
     *
     * @param  string               $env1 First environment
     * @param  string               $env2 Second environment
     * @return array<string, mixed> Comparison result
     */
    public function diff(string $env1, string $env2): array
    {
        // Key by path without environment for comparison (e.g., database.main instead of database.production.main)
        $stripEnv = fn (Node $n): string => $this->stripEnvironmentFromPath($n);

        $nodes1 = $this->matching(['environment' => $env1])->keyBy($stripEnv);
        $nodes2 = $this->matching(['environment' => $env2])->keyBy($stripEnv);

        $only1 = $nodes1->diffKeys($nodes2)->keys()->all();
        $only2 = $nodes2->diffKeys($nodes1)->keys()->all();
        $both = $nodes1->intersectByKeys($nodes2)->keys()->all();

        $differences = [];

        foreach ($both as $key) {
            $n1 = $nodes1->get($key);
            $n2 = $nodes2->get($key);

            if ($n1 === null) {
                continue;
            }

            if ($n2 === null) {
                continue;
            }

            $fieldDiff = $this->compareNodes($n1, $n2, $env1, $env2);

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
     * @param string      $filepath Path to the configuration file
     * @param null|string $key      Encryption key (generates one if null)
     * @param null|string $cipher   Cipher algorithm (default from config or AES-256-CBC)
     * @param bool        $prune    Delete the original file after encryption (default: false)
     * @param bool        $force    Overwrite existing encrypted file (default: false)
     * @param null|string $env      Environment name (e.g., 'production')
     * @param null|string $envStyle Environment style: 'suffix' or 'directory'
     *
     * @throws EncryptionFailedException If encryption fails
     * @throws FileNotFoundException     If file doesn't exist
     *
     * @return array{path: string, key: string} The encrypted file path and key
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

        $sourcePath = $this->resolveEnvFilePath($filepath, $env, $envStyle);

        if (!file_exists($sourcePath)) {
            throw FileNotFoundException::forPath($sourcePath);
        }

        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw ReadOnlyConfigurationException::forPath($sourcePath);
        }

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
     * @param string      $encryptedPath Path to the encrypted file
     * @param string      $key           The decryption key
     * @param bool        $force         Overwrite existing decrypted file (default: false)
     * @param null|string $cipher        Cipher algorithm
     * @param null|string $path          Custom output directory path
     * @param null|string $filename      Custom output filename
     * @param null|string $env           Environment name
     * @param bool        $prune         Delete the encrypted file after decryption (default: false)
     * @param null|string $envStyle      Environment style
     *
     * @throws DecryptionFailedException If decryption fails
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

        $sourcePath = $env !== null
            ? $this->resolveEnvFilePath($encryptedPath, $env, $envStyle).'.encrypted'
            : $encryptedPath;

        if (!file_exists($sourcePath)) {
            throw FileNotFoundException::forPath($sourcePath);
        }

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
     * @param string      $directory Path to the directory to encrypt
     * @param null|string $key       Encryption key (generates one if null)
     * @param null|string $cipher    Cipher algorithm
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
     * @param string      $directory Path to the directory containing encrypted files
     * @param string      $key       The decryption key
     * @param bool        $force     Overwrite existing decrypted files (default: false)
     * @param null|string $cipher    Cipher algorithm
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
     * Strip environment from a node's path for cross-environment comparison.
     *
     * Removes the environment segment (index 1) from paths like "database.production.main"
     * to produce "database.main" for comparison purposes.
     *
     * @param Node $node The node to extract path from
     *
     * @return string Path with environment stripped (e.g., "database.main")
     */
    private function stripEnvironmentFromPath(Node $node): string
    {
        $path = $node->path;

        // Path structure: [partition, environment, ...rest]
        // Remove index 1 (environment) for comparison
        if (count($path) >= 2) {
            unset($path[1]);
            $path = array_values($path);
        }

        return implode('.', $path);
    }

    /**
     * Compare two nodes field by field.
     *
     * @param  Node                                $n1   First node
     * @param  Node                                $n2   Second node
     * @param  string                              $env1 First environment name
     * @param  string                              $env2 Second environment name
     * @return array<string, array<string, mixed>> Field differences
     */
    private function compareNodes(Node $n1, Node $n2, string $env1, string $env2): array
    {
        $diff = [];
        $allFields = array_merge($n1->fieldNames(), $n2->fieldNames());
        $allFields = array_unique($allFields);

        foreach ($allFields as $field) {
            $v1 = $n1->get($field);
            $v2 = $n2->get($field);

            if ($v1 === $v2) {
                continue;
            }

            $diff[$field] = [
                $env1 => $v1,
                $env2 => $v2,
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

        $matches = glob($directory.DIRECTORY_SEPARATOR.$pattern, GLOB_NOSORT);

        if ($matches !== false) {
            foreach ($matches as $match) {
                if (!is_file($match)) {
                    continue;
                }

                if (str_ends_with($match, '.encrypted')) {
                    continue;
                }

                $files[] = $match;
            }
        }

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

        $matches = glob($directory.DIRECTORY_SEPARATOR.'*.encrypted', GLOB_NOSORT);

        if ($matches !== false) {
            foreach ($matches as $match) {
                if (!is_file($match)) {
                    continue;
                }

                $files[] = $match;
            }
        }

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
     * Parse the encryption key from base64 encoding.
     *
     * @param string $key Base64-encoded encryption key
     *
     * @throws InvalidBase64KeyException If the key is not valid base64
     *
     * @return string Decoded binary encryption key
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
     * @param  string      $filepath Base file path
     * @param  null|string $env      Environment name
     * @param  null|string $envStyle Environment style: 'suffix' or 'directory'
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
                return $directory.DIRECTORY_SEPARATOR.$envDirectory.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.$filename;
            }

            return $directory.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.$filename;
        }

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
        $baseFilename = preg_match('/\.encrypted$/', $encryptedPath)
            ? basename($encryptedPath, '.encrypted')
            : basename($encryptedPath).'.decrypted';

        $outputFilename = $filename ?? $baseFilename;
        $outputDir = $path ?? dirname($encryptedPath);

        return mb_rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$outputFilename;
    }
}
