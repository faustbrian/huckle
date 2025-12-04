<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Facades;

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\Credential;
use Cline\Huckle\Parser\Group;
use Cline\Huckle\Parser\HuckleConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Huckle credential manager.
 *
 * @method static array<string, string>                                              allExports()
 * @method static HuckleConfig                                                       config()
 * @method static string|null                                                        connection(string $path, string $connectionName)
 * @method static Collection<string, Credential>                                     credentials()
 * @method static string                                                             decrypt(string $encryptedPath, string $key, bool $force = false, ?string $cipher = null, ?string $path = null, ?string $filename = null, ?string $env = null, bool $prune = false, ?string $envStyle = null)
 * @method static array<string>                                                      decryptDirectory(string $directory, string $key, bool $force = false, ?string $cipher = null, bool $prune = false, bool $recursive = false)
 * @method static array<string, mixed>                                               diff(string $env1, string $env2)
 * @method static array{path: string, key: string}                                   encrypt(string $filepath, ?string $key = null, ?string $cipher = null, bool $prune = false, bool $force = false, ?string $env = null, ?string $envStyle = null)
 * @method static array{files: array<array{path: string, key: string}>, key: string} encryptDirectory(string $directory, ?string $key = null, ?string $cipher = null, bool $prune = false, bool $force = false, bool $recursive = false, ?string $glob = null)
 * @method static Collection<string, Credential>                                     expired()
 * @method static Collection<string, Credential>                                     expiring(?int $days = null)
 * @method static HuckleManager                                                      exportAllToEnv()
 * @method static array<string, string>                                              exports(string $path)
 * @method static HuckleManager                                                      exportToEnv(string $path)
 * @method static HuckleManager                                                      flush()
 * @method static Credential|null                                                    get(string $path)
 * @method static string                                                             getConfigPath()
 * @method static Group|null                                                         group(string $path)
 * @method static Collection<string, Group>                                          groups()
 * @method static bool                                                               has(string $path)
 * @method static Collection<string, Credential>                                     inEnvironment(string $environment)
 * @method static Collection<string, Credential>                                     inGroup(string $group, ?string $environment = null)
 * @method static HuckleConfig                                                       load(?string $path = null)
 * @method static array<string, string>                                              loadEnv(string $path, array<string, string> $context)
 * @method static Collection<string, Credential>                                     needsRotation(?int $days = null)
 * @method static Collection<string, Credential>                                     tagged(string ...$tags)
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
     * @return string The service container binding key
     */
    protected static function getFacadeAccessor(): string
    {
        return HuckleManager::class;
    }
}
