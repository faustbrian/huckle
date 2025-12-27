<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Facades;

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\HuckleConfig;
use Cline\Huckle\Parser\Node;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Huckle configuration manager.
 *
 * Provides static access to HuckleManager methods for configuration management,
 * including loading, querying, encryption/decryption, and environment exports.
 * All method calls are proxied to the underlying HuckleManager instance.
 *
 * @method static array<string, string>                                              allExports()
 * @method static HuckleConfig                                                       config()
 * @method static array<string, string>                                              configsForContext(array<string, string> $context)
 * @method static string|null                                                        connection(string $path, string $connectionName)
 * @method static string                                                             decrypt(string $encryptedFilePath, string $key, bool $force = false, ?string $cipher = null, ?string $path = null, ?string $filename = null, ?string $env = null, bool $prune = false, ?string $envStyle = null)
 * @method static array<string>                                                      decryptDirectory(string $directory, string $key, bool $force = false, ?string $cipher = null, bool $prune = false, bool $recursive = false)
 * @method static array<string, mixed>                                               diff(string $env1, string $env2)
 * @method static HuckleManager                                                      disable()
 * @method static HuckleManager                                                      enable()
 * @method static array{path: string, key: string}                                   encrypt(string $filePath, ?string $key = null, ?string $cipher = null, bool $prune = false, bool $force = false, ?string $env = null, ?string $envStyle = null)
 * @method static array{files: array<array{path: string, key: string}>, key: string} encryptDirectory(string $directory, ?string $key = null, ?string $cipher = null, bool $prune = false, bool $force = false, bool $recursive = false, ?string $glob = null)
 * @method static Collection<string, Node>                                           expired()
 * @method static Collection<string, Node>                                           expiring(?int $days = null)
 * @method static HuckleManager                                                      exportAllToEnv()
 * @method static HuckleManager                                                      exportContextToConfig(array<string, string> $context)
 * @method static HuckleManager                                                      exportContextToEnv(array<string, string> $context)
 * @method static array<string, string>                                              exports(string $path)
 * @method static array<string, string>                                              exportsForContext(array<string, string> $context)
 * @method static HuckleManager                                                      exportToEnv(string $path)
 * @method static HuckleManager                                                      flush()
 * @method static Node|null                                                          get(string $path)
 * @method static string                                                             getConfigPath()
 * @method static bool                                                               has(string $path)
 * @method static bool                                                               isDisabled()
 * @method static bool                                                               isEnabled()
 * @method static HuckleConfig                                                       load(?string $path = null)
 * @method static array<string, string>                                              loadEnv(string $path, array<string, string> $context)
 * @method static array<string, string>                                              mappings()
 * @method static Collection<string, Node>                                           matching(array<string, string> $context)
 * @method static Collection<string, Node>                                           needsRotation(?int $days = null)
 * @method static Collection<string, Node>                                           nodes()
 * @method static Collection<string, Node>                                           partitions()
 * @method static Collection<string, Node>                                           tagged(string ...$tags)
 * @method static array<string, mixed>                                               validate(?string $path = null)
 *
 * @author Brian Faust <brian@cline.sh>
 * @see HuckleManager
 */
final class Huckle extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string Service container binding key for HuckleManager resolution
     */
    protected static function getFacadeAccessor(): string
    {
        return HuckleManager::class;
    }
}
