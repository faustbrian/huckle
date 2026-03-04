# Config Cache Compatibility

Make Huckle work seamlessly with Laravel's `config:cache` command.

**Use case:** Production deployments where `php artisan config:cache` is used for performance optimization.

## The Problem

When you run `php artisan config:cache`, Laravel compiles all configuration files into a single cached file. After caching, `env()` calls return `null` because Laravel no longer reads from environment variables.

Traditional Huckle usage with `exportContextToEnv()` sets `$_ENV`, `$_SERVER`, and `putenv()` — but these are ignored by cached configs.

## The Solution

Huckle provides three mechanisms to write directly to Laravel's config repository, bypassing `env()` entirely:

1. **`config` blocks** — Define config paths directly in your HCL nodes
2. **`mappings` block** — Global env-to-config path mappings in HCL
3. **PHP config mappings** — Define mappings in `config/huckle.php`

## Method 1: Config Blocks (Per-Node)

Define config paths directly alongside your exports:

```hcl
partition "payments" {
  environment "production" {
    provider "stripe" {
      api_key    = sensitive("pk_live_xxx")
      api_secret = sensitive("sk_live_xxx")

      # Traditional env exports (still useful for non-cached scenarios)
      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }

      # Direct Laravel config paths
      config {
        "cashier.key"         = self.api_key
        "cashier.secret"      = self.api_secret
        "services.stripe.key" = self.api_key
      }
    }
  }
}
```

## Method 2: Global Mappings Block

Define a top-level `mappings` block to map environment variable names to config paths:

```hcl
# Global mappings apply to all exports with matching keys
mappings {
  STRIPE_KEY        = "cashier.key"
  STRIPE_SECRET     = "cashier.secret"
  REDIS_HOST        = "database.redis.default.host"
  REDIS_PASSWORD    = "database.redis.default.password"
  DB_HOST           = "database.connections.pgsql.host"
  DB_PASSWORD       = "database.connections.pgsql.password"
}

partition "payments" {
  environment "production" {
    provider "stripe" {
      api_key = sensitive("pk_live_xxx")

      export {
        STRIPE_KEY = self.api_key  # Will also write to cashier.key via mapping
      }
    }
  }
}
```

## Method 3: PHP Config Mappings

Define mappings in your `config/huckle.php`:

```php
return [
    'path' => base_path('config/huckle.hcl'),

    'mappings' => [
        'STRIPE_KEY'    => 'cashier.key',
        'STRIPE_SECRET' => 'cashier.secret',
        'REDIS_HOST'    => 'database.redis.default.host',
        'DB_HOST'       => 'database.connections.pgsql.host',
    ],
];
```

## Precedence

When multiple mapping sources exist, they're applied in this order (later overrides earlier):

1. PHP config mappings (`config/huckle.php`)
2. HCL `mappings` block
3. Node `config` blocks (highest precedence)

## Usage in Code

### Standard Usage (env + config)

```php
use Cline\Huckle\Facades\Huckle;

// Writes to both env AND Config
Huckle::exportContextToEnv([
    'partition' => 'payments',
    'environment' => 'production',
]);

// Both work:
$key = getenv('STRIPE_KEY');      // 'pk_live_xxx'
$key = config('cashier.key');      // 'pk_live_xxx'
```

### Config-Only Usage (for config:cache)

```php
use Cline\Huckle\Facades\Huckle;

// Writes ONLY to Config (no env)
Huckle::exportContextToConfig([
    'partition' => 'payments',
    'environment' => 'production',
]);

// Only config works (env is not set):
$key = config('cashier.key');  // 'pk_live_xxx'
```

## Production Setup

### Service Provider Integration

Create a service provider that loads Huckle config early in the boot process:

```php
<?php

namespace App\Providers;

use Cline\Huckle\Facades\Huckle;
use Illuminate\Support\ServiceProvider;

class HuckleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Determine context from your application
        $context = [
            'partition' => config('app.tenant', 'default'),
            'environment' => app()->environment(),
        ];

        // Use exportContextToConfig for config:cache compatibility
        Huckle::exportContextToConfig($context);
    }
}
```

Register it early in `config/app.php`:

```php
'providers' => [
    // Load before other providers that need the config values
    App\Providers\HuckleServiceProvider::class,

    // Other providers...
],
```

### Multi-Tenant Example

```php
<?php

namespace App\Http\Middleware;

use Cline\Huckle\Facades\Huckle;
use Closure;
use Illuminate\Http\Request;

class ApplyTenantConfig
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->route('tenant') ?? session('tenant');

        if ($tenant) {
            Huckle::exportContextToConfig([
                'partition' => $tenant,
                'environment' => app()->environment(),
            ]);
        }

        return $next($request);
    }
}
```

## Complete Example

```hcl
# config/huckle.hcl

# Global mappings for common env vars
mappings {
  DB_HOST       = "database.connections.pgsql.host"
  DB_PORT       = "database.connections.pgsql.port"
  DB_DATABASE   = "database.connections.pgsql.database"
  DB_USERNAME   = "database.connections.pgsql.username"
  DB_PASSWORD   = "database.connections.pgsql.password"
  REDIS_HOST    = "database.redis.default.host"
  STRIPE_KEY    = "cashier.key"
  STRIPE_SECRET = "cashier.secret"
}

partition "US" {
  environment "production" {
    provider "database" {
      host     = "db-us.prod.internal"
      port     = 5432
      database = "app_us"
      username = "app_user"
      password = sensitive("prod_password")

      export {
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_DATABASE = self.database
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
      }
    }

    provider "stripe" {
      api_key    = sensitive("pk_live_us_xxx")
      api_secret = sensitive("sk_live_us_xxx")

      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }

      # Additional config paths not in global mappings
      config {
        "services.stripe.webhook_secret" = sensitive("whsec_us_xxx")
      }
    }
  }
}

partition "EU" {
  environment "production" {
    provider "database" {
      host     = "db-eu.prod.internal"
      port     = 5432
      database = "app_eu"
      username = "app_user"
      password = sensitive("eu_prod_password")

      export {
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_DATABASE = self.database
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
      }
    }

    provider "stripe" {
      api_key    = sensitive("pk_live_eu_xxx")
      api_secret = sensitive("sk_live_eu_xxx")

      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }
    }
  }
}
```

```php
// app/Providers/HuckleServiceProvider.php

public function boot(): void
{
    $region = config('app.region', 'US');

    Huckle::exportContextToConfig([
        'partition' => $region,
        'environment' => app()->environment(),
    ]);

    // Now these work even with config:cache:
    // config('database.connections.pgsql.host') => 'db-us.prod.internal'
    // config('cashier.key') => 'pk_live_us_xxx'
}
```

## Verifying It Works

```bash
# Cache your config
php artisan config:cache

# Verify values are accessible
php artisan tinker --execute="dump(config('cashier.key'))"
# Should output your Stripe key, not null

# Clear cache for development
php artisan config:clear
```

## API Reference

### Facade Methods

```php
// Export to both env and Config (applies mappings + config blocks)
Huckle::exportContextToEnv(array $context): HuckleManager

// Export only to Config (for config:cache compatibility)
Huckle::exportContextToConfig(array $context): HuckleManager

// Get config mappings for a context
Huckle::configsForContext(array $context): array

// Get all mappings (HCL + PHP config merged)
Huckle::mappings(): array
```

### HuckleConfig Methods

```php
$config = Huckle::config();

// Get global mappings from HCL
$config->mappings(): array

// Get specific mapping
$config->mapping('STRIPE_KEY'): ?string

// Check if mapping exists
$config->hasMapping('STRIPE_KEY'): bool

// Get configs for a node path
$config->configs('payments.production.stripe'): array

// Get all configs matching context
$config->configsForContext(['partition' => 'US']): array
```

### Node Methods

```php
$node = Huckle::get('payments.production.stripe');

// Get resolved config mappings
$node->config(): array  // ['cashier.key' => 'pk_live_xxx', ...]

// Access raw configs property
$node->configs  // ['cashier.key' => 'self.api_key', ...] (unresolved)
```
