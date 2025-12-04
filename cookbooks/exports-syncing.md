# Exports & Syncing

Export credential values as environment variables and sync them to your .env file.

**Use case:** Keeping .env files in sync with centralized credential management.

## Defining Exports

Define which fields should be exported as environment variables:

```hcl
group "database" "production" {
  credential "main" {
    host     = "db.prod.internal"
    port     = 5432
    username = "app_user"
    password = sensitive("secret123")
    database = "myapp"

    export {
      # Simple field mapping
      DB_HOST     = self.host
      DB_PORT     = self.port
      DB_USERNAME = self.username
      DB_PASSWORD = self.password
      DB_DATABASE = self.database

      # Interpolated values
      DATABASE_URL = "postgres://${self.username}:${self.password}@${self.host}:${self.port}/${self.database}"
    }
  }
}
```

## Exporting to Environment

### In Code

```php
use Cline\Huckle\Facades\Huckle;

// Export a single credential
Huckle::exportToEnv('database.production.main');

// Now these are available:
$host = getenv('DB_HOST');        // 'db.prod.internal'
$url = getenv('DATABASE_URL');    // 'postgres://app_user:secret123@...'

// Export all credentials
Huckle::exportAllToEnv();
```

### Via Artisan

```bash
# Export as .env format (default)
php artisan huckle:export

# Output:
# DB_HOST=db.prod.internal
# DB_PORT=5432
# DB_USERNAME=app_user
# DB_PASSWORD=secret123
# DATABASE_URL="postgres://..."

# Export as JSON
php artisan huckle:export --format=json

# Export as shell commands
php artisan huckle:export --format=shell

# Output:
# export DB_HOST='db.prod.internal'
# export DB_PORT='5432'
```

### Filtered Exports

```bash
# Export specific credential
php artisan huckle:export --path=database.production.main

# Export by environment
php artisan huckle:export --env=production

# Export by group
php artisan huckle:export --group=database

# Export by tag
php artisan huckle:export --tag=critical
```

## Syncing to .env File

### Sync Command

```bash
# Preview changes (dry run)
php artisan huckle:sync --dry-run

# Output:
# Would update .env with 5 variables:
#   DB_HOST: [not set] -> db.prod.internal
#   DB_PORT: 3306 -> 5432
#   DB_USERNAME: old_user -> app_user

# Apply changes
php artisan huckle:sync

# Sync to specific env file
php artisan huckle:sync --env-file=.env.production
```

### Sync Options

```bash
# Sync specific credential
php artisan huckle:sync --path=database.production.main

# Sync by environment
php artisan huckle:sync --env=production

# Sync by group
php artisan huckle:sync --group=database
```

## Auto-Export on Boot

Enable automatic export in your config:

```php
// config/huckle.php
return [
    'path' => base_path('credentials.hcl'),
    'auto_export' => true,  // Export all on application boot
];
```

## Getting Exports Programmatically

```php
use Cline\Huckle\Facades\Huckle;

// Get exports for a specific credential
$exports = Huckle::exports('database.production.main');
// ['DB_HOST' => 'db.prod.internal', 'DB_PORT' => '5432', ...]

// Get all exports from all credentials
$allExports = Huckle::allExports();
```

## Complete Example: CI/CD Integration

```php
// In a deployment script or service provider

use Cline\Huckle\Facades\Huckle;

class DeploymentCredentialService
{
    public function exportForEnvironment(string $environment): void
    {
        // Get all credentials for this environment
        $credentials = Huckle::inEnvironment($environment);

        foreach ($credentials as $credential) {
            $path = "{$credential->group}.{$credential->environment}.{$credential->name}";
            Huckle::exportToEnv($path);
        }
    }

    public function generateEnvFile(string $environment, string $outputPath): void
    {
        $exports = [];

        foreach (Huckle::inEnvironment($environment) as $credential) {
            $path = "{$credential->group}.{$credential->environment}.{$credential->name}";
            $exports = array_merge($exports, Huckle::exports($path));
        }

        $content = '';
        foreach ($exports as $key => $value) {
            $content .= "{$key}={$this->escapeValue($value)}\n";
        }

        file_put_contents($outputPath, $content);
    }

    private function escapeValue(string $value): string
    {
        if (preg_match('/[\s#"\']/', $value)) {
            return '"' . addslashes($value) . '"';
        }
        return $value;
    }
}
```

```bash
# Generate env file for production deployment
php artisan tinker --execute="app(DeploymentCredentialService::class)->generateEnvFile('production', '/tmp/.env.production')"
```
