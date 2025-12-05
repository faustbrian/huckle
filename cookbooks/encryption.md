# Configuration Encryption

Encrypt and decrypt HCL configuration files for secure storage and deployment using Laravel's Encrypter with AES-256-CBC.

**Use cases:** Encrypting sensitive configs at rest, storing encrypted configs in version control, and decrypting during deployment.

## Basic Encryption

```php
use Cline\Huckle\Facades\Huckle;

// Encrypt a configuration file (generates a new key)
$result = Huckle::encrypt('/path/to/credentials.hcl');

// $result contains:
// [
//     'path' => '/path/to/credentials.hcl.encrypted',  // The encrypted file
//     'key'  => 'base64:ABC123...',                    // Store this securely!
// ]
```

> **Important:** Save the key securely (environment variable, secret manager, etc.). You'll need it to decrypt the file later.

## Decryption

```php
// Decrypt using the key from encryption
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    'base64:ABC123...',  // The key from encrypt()
);

// Returns: '/path/to/credentials.hcl' (original filename without .encrypted)
```

## Using a Custom Key

```php
// Generate your own key (must be valid for AES-256-CBC = 32 bytes)
$myKey = 'base64:' . base64_encode(random_bytes(32));

// Encrypt with your key
$result = Huckle::encrypt('/path/to/config.hcl', $myKey);

// Decrypt with the same key
Huckle::decrypt('/path/to/config.hcl.encrypted', $myKey);
```

## Force Overwrite

```php
// By default, decrypt() throws if the target file already exists
// Use force: true to overwrite
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    force: true,
);
```

## Custom Cipher

```php
// Use a different cipher (default is AES-256-CBC)
$result = Huckle::encrypt(
    '/path/to/config.hcl',
    cipher: 'AES-128-CBC',  // 16-byte key
);

// Decrypt with matching cipher
Huckle::decrypt(
    '/path/to/config.hcl.encrypted',
    $result['key'],
    cipher: 'AES-128-CBC',
);
```

## CLI Commands

```bash
# Encrypt a file (generates and displays key)
php artisan huckle:encrypt config/credentials.hcl

# Encrypt with a specific key
php artisan huckle:encrypt config/credentials.hcl --key="base64:ABC123..."

# Encrypt using APP_KEY
php artisan huckle:encrypt config/credentials.hcl --app-key

# Encrypt and delete original
php artisan huckle:encrypt config/credentials.hcl --prune

# Decrypt a file
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="base64:ABC123..."

# Decrypt using APP_KEY
php artisan huckle:decrypt config/credentials.hcl.encrypted --app-key

# Decrypt with force overwrite
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="..." --force

# Decrypt to custom path
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="..." --path=/var/www/config

# Decrypt with custom filename
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="..." --filename=decrypted.hcl

# Use custom cipher
php artisan huckle:encrypt config/credentials.hcl --cipher=AES-128-CBC
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="..." --cipher=AES-128-CBC

# Environment-specific encryption (suffix style)
php artisan huckle:encrypt config/credentials.hcl --env=production
php artisan huckle:decrypt config/credentials.hcl --key="..." --env=production

# Environment-specific encryption (directory style)
php artisan huckle:encrypt config/credentials.hcl --env=production --env-style=directory
php artisan huckle:decrypt config/credentials.hcl --key="..." --env=production --env-style=directory
```

## Environment-Specific Files: Suffix Style (Default)

Suffix style transforms `config.hcl` → `config.production.hcl`. This matches Laravel's `.env` pattern.

```php
$result = Huckle::encrypt(
    '/path/to/config.hcl',
    env: 'production',  // Encrypts /path/to/config.production.hcl
);

// Decrypts /path/to/config.production.hcl.encrypted
Huckle::decrypt(
    '/path/to/config.hcl',
    $result['key'],
    env: 'production',
);
```

## Environment-Specific Files: Directory Style

Directory style transforms `config/credentials/db.hcl` → `config/credentials/production/db.hcl`. Perfect for organizing configs in environment subdirectories.

```php
$result = Huckle::encrypt(
    '/path/to/config/credentials/db.hcl',
    env: 'production',
    envStyle: 'directory',
);

Huckle::decrypt(
    '/path/to/config/credentials/db.hcl',
    $result['key'],
    env: 'production',
    envStyle: 'directory',
);
```

## Prune Option

Delete the source file after the operation completes.

```php
// Delete the original file after encryption
$result = Huckle::encrypt('/path/to/credentials.hcl', prune: true);
// /path/to/credentials.hcl is deleted, only .encrypted remains

// Delete the encrypted file after decryption
Huckle::decrypt('/path/to/credentials.hcl.encrypted', $key, prune: true);
// .encrypted file is deleted, only decrypted file remains
```

## Custom Output Location

```php
// Output to a different directory
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    path: '/var/www/app/config',
);
// Returns: /var/www/app/config/credentials.hcl

// Use a custom filename
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    filename: 'decrypted-credentials.hcl',
);
// Returns: /path/to/decrypted-credentials.hcl

// Combine path and filename
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    path: '/var/www/app/config',
    filename: 'app-credentials.hcl',
);
// Returns: /var/www/app/config/app-credentials.hcl
```

## Directory Encryption

Encrypt all files in a directory with a single key. Perfect for encrypting entire config directories like `.huckle/`.

```php
use Cline\Huckle\Facades\Huckle;

// Encrypt all files in a directory
$result = Huckle::encryptDirectory('/path/to/.huckle');

// $result contains:
// [
//     'files' => [
//         ['path' => '/path/to/.huckle/config.hcl.encrypted', 'key' => '...'],
//         ['path' => '/path/to/.huckle/secrets.hcl.encrypted', 'key' => '...'],
//     ],
//     'key' => 'base64:ABC123...',  // Same key for all files
// ]
```

### Recursive Directory Encryption

```php
// Encrypt files in subdirectories too
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    recursive: true,
);
```

### Filter Files with Glob Pattern

```php
// Only encrypt HCL files
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    glob: '*.hcl',
);

// Encrypt HCL files recursively
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    recursive: true,
    glob: '*.hcl',
);
```

### Directory Decryption

```php
// Decrypt all .encrypted files in directory
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    'base64:ABC123...',  // Key from encryptDirectory()
);

// Returns array of decrypted file paths:
// ['/path/to/.huckle/config.hcl', '/path/to/.huckle/secrets.hcl']

// Recursive decryption
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    $key,
    recursive: true,
);
```

### Prune and Force Options

```php
// Delete originals after encryption
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    prune: true,
);

// Overwrite existing encrypted files
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    force: true,
);

// Delete encrypted files after decryption, overwrite existing
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    $key,
    prune: true,
    force: true,
);
```

### CLI Commands for Directories

```bash
# Encrypt directory
php artisan huckle:encrypt .huckle

# Encrypt recursively with glob filter
php artisan huckle:encrypt .huckle --recursive --glob='*.hcl'

# Delete originals after encryption
php artisan huckle:encrypt .huckle --recursive --prune

# Decrypt directory
php artisan huckle:decrypt .huckle --key="base64:ABC123..."

# Decrypt recursively, keep encrypted files
php artisan huckle:decrypt .huckle --key="..." --recursive --keep
```

## Complete Example: Secure Deployment Workflow

```php
/**
 * Encrypt sensitive configs before committing to version control.
 * Run this locally before pushing code.
 */
function encryptForDeployment(): void
{
    $sensitiveFiles = [
        base_path('config/huckle.hcl'),
        base_path('config/credentials.hcl'),
        base_path('config/api-keys.hcl'),
    ];

    $deployKey = env('CONFIG_ENCRYPTION_KEY');

    foreach ($sensitiveFiles as $filepath) {
        if (file_exists($filepath)) {
            Huckle::encrypt($filepath, $deployKey);
            unlink($filepath);  // Delete unencrypted version
            echo "Encrypted: {$filepath}\n";
        }
    }
}

/**
 * Decrypt sensitive configs during deployment.
 * Run this on the server after pulling code.
 */
function decryptForRuntime(): void
{
    $encryptedFiles = glob(base_path('config/*.encrypted')) ?: [];
    $deployKey = env('CONFIG_ENCRYPTION_KEY');

    foreach ($encryptedFiles as $encryptedPath) {
        Huckle::decrypt($encryptedPath, $deployKey, force: true);
        unlink($encryptedPath);  // Delete encrypted version
        echo "Decrypted: {$encryptedPath}\n";
    }
}
```

## Complete Example: Per-Environment Keys

```php
/**
 * Each environment has its own encryption key.
 * Encrypt once per environment, store encrypted files in version control.
 */
function encryptForEnvironment(string $environment): void
{
    $keyEnvVar = mb_strtoupper($environment) . '_CONFIG_KEY';
    $key = env($keyEnvVar);

    if ($key === null) {
        throw new RuntimeException("Missing encryption key: {$keyEnvVar}");
    }

    $configPath = base_path("config/credentials.{$environment}.hcl");
    $result = Huckle::encrypt($configPath, $key);

    echo "Encrypted {$configPath} -> {$result['path']}\n";
}

// Usage:
// PRODUCTION_CONFIG_KEY=base64:xxx encryptForEnvironment('production');
// STAGING_CONFIG_KEY=base64:yyy encryptForEnvironment('staging');
```

## Complete Example: Key Rotation

```php
function rotateEncryptionKey(string $filepath, string $oldKey, string $newKey): void
{
    // Decrypt with old key
    $decryptedPath = Huckle::decrypt($filepath, $oldKey, force: true);

    // Re-encrypt with new key
    Huckle::encrypt($decryptedPath, $newKey);

    // Clean up unencrypted file
    unlink($decryptedPath);
}
```

## Complete Example: Directory-Style Environment Workflow

```php
/**
 * Encrypt credential configs for deployment.
 * Structure: config/credentials/{env}/database.hcl
 */
function encryptCredentialConfigs(string $environment): void
{
    $key = env(mb_strtoupper($environment) . '_CONFIG_KEY');
    $types = ['database', 'cache', 'queue'];

    foreach ($types as $type) {
        $basePath = base_path("config/credentials/{$type}.hcl");

        Huckle::encrypt(
            $basePath,
            $key,
            env: $environment,
            envStyle: 'directory',
            prune: true,
        );
    }
}

/**
 * Decrypt credential configs during deployment.
 */
function decryptCredentialConfigs(string $environment): void
{
    $key = env(mb_strtoupper($environment) . '_CONFIG_KEY');
    $types = ['database', 'cache', 'queue'];

    foreach ($types as $type) {
        $basePath = base_path("config/credentials/{$type}.hcl");

        Huckle::decrypt(
            $basePath,
            $key,
            env: $environment,
            envStyle: 'directory',
            prune: true,
            force: true,
        );
    }
}
```
