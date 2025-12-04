# Environment Management

Organize credentials by environment (production, staging, development) and filter them for specific use cases.

**Use case:** Managing the same service credentials across multiple deployment environments.

## Organizing by Environment

Structure your credentials with environment labels:

```hcl
# Production database
group "database" "production" {
  tags = ["prod", "postgres"]

  credential "main" {
    host     = "db.prod.internal"
    port     = 5432
    username = "app_user"
    password = sensitive("prod-secret")
  }
}

# Staging database
group "database" "staging" {
  tags = ["staging", "postgres"]

  credential "main" {
    host     = "db.staging.internal"
    port     = 5432
    username = "app_user"
    password = sensitive("staging-secret")
  }
}

# Development database
group "database" "development" {
  tags = ["dev", "postgres"]

  credential "main" {
    host     = "localhost"
    port     = 5432
    username = "dev_user"
    password = sensitive("dev-secret")
  }
}
```

## Filtering by Environment

```php
use Cline\Huckle\Facades\Huckle;

// Get all production credentials
$prodCredentials = Huckle::inEnvironment('production');

// Get all staging credentials
$stagingCredentials = Huckle::inEnvironment('staging');

// Get credentials from multiple environments (OR logic)
$nonProdCredentials = Huckle::inEnvironment(['local', 'sandbox', 'staging']);

// Iterate over environment credentials
foreach (Huckle::inEnvironment('production') as $credential) {
    echo "{$credential->path}: {$credential->host}\n";
}
```

## Filtering by Group

```php
// Get all database credentials (any environment)
$databases = Huckle::inGroup('database');

// Get database credentials for production only
$prodDatabases = Huckle::inGroup('database', 'production');
```

## Filtering by Tags

```php
// Get all credentials tagged 'critical'
$critical = Huckle::tagged('critical');

// Get credentials with multiple tags (AND logic)
$prodPostgres = Huckle::tagged('prod', 'postgres');
```

## Comparing Environments

Use the diff command to compare credentials between environments:

```bash
php artisan huckle:diff production staging

# Output:
# Comparing production vs staging
#
# Only in production:
#   - database.production.readonly
#
# Only in staging:
#   - database.staging.test
#
# Differences:
#   database.*.main:
#     host: db.prod.internal -> db.staging.internal
```

## Dynamic Environment Selection

```php
use Cline\Huckle\Facades\Huckle;

class CredentialService
{
    public function getForEnvironment(string $group, string $name): ?Credential
    {
        $env = app()->environment(); // 'production', 'staging', etc.

        return Huckle::get("{$group}.{$env}.{$name}");
    }

    public function getDatabaseCredential(): ?Credential
    {
        return $this->getForEnvironment('database', 'main');
    }
}

// Usage
$credential = app(CredentialService::class)->getDatabaseCredential();
```

## Environment-Specific Exports

Export credentials for the current environment:

```bash
# Export only production credentials
php artisan huckle:export --environment=production

# Export only staging credentials
php artisan huckle:export --environment=staging --format=shell
```

## Complete Example: Multi-Environment Setup

```hcl
# credentials.hcl

defaults {
  owner = "platform-team"
}

# AWS credentials per environment
group "aws" "production" {
  tags = ["prod", "aws"]

  credential "deploy" {
    access_key = sensitive("AKIAPRODUCTION...")
    secret_key = sensitive("...")
    region     = "us-east-1"

    export {
      AWS_ACCESS_KEY_ID     = self.access_key
      AWS_SECRET_ACCESS_KEY = self.secret_key
      AWS_DEFAULT_REGION    = self.region
    }
  }
}

group "aws" "staging" {
  tags = ["staging", "aws"]

  credential "deploy" {
    access_key = sensitive("AKIASTAGING...")
    secret_key = sensitive("...")
    region     = "us-west-2"

    export {
      AWS_ACCESS_KEY_ID     = self.access_key
      AWS_SECRET_ACCESS_KEY = self.secret_key
      AWS_DEFAULT_REGION    = self.region
    }
  }
}
```

```php
// Automatically export credentials based on environment
$env = app()->environment();
$awsCredential = Huckle::get("aws.{$env}.deploy");

if ($awsCredential) {
    Huckle::exportToEnv("aws.{$env}.deploy");
}
```

## Partition and Fallback Blocks

For multi-tenant or geographic configurations, use partition blocks and `fallback` blocks. Partition blocks can use any of these keywords (all have identical semantics):

- `partition` - generic term
- `tenant` - for multi-tenant applications
- `namespace` - for namespace-based isolation
- `division` - for business divisions
- `entity` - for legal entities or organizations

Choose whichever term best fits your domain. Fallback blocks provide default exports that apply when no matching partition is found, or as a base layer that partitions can override.

```hcl
# Fallback provides shared defaults for all partitions
fallback {
  environment "production" {
    provider "service_a" {
      key    = "pk_live_default"
      secret = "sk_live_default"

      export {
        SERVICE_A_KEY    = self.key
        SERVICE_A_SECRET = self.secret
      }
    }

    provider "service_b" {
      api_key = "default-api-key"

      export {
        SERVICE_B_API_KEY = self.api_key
      }
    }
  }

  environment "staging" "local" "sandbox" {
    provider "service_a" {
      key    = "pk_test_default"
      secret = "sk_test_default"

      export {
        SERVICE_A_KEY    = self.key
        SERVICE_A_SECRET = self.secret
      }
    }
  }
}

# Using 'tenant' keyword for multi-tenant app
tenant "FI" {
  environment "production" {
    provider "provider_fi" {
      customer_number = "12345-FI"
      api_key         = "fi-provider-key"

      export {
        PROVIDER_FI_CUSTOMER_NUMBER = self.customer_number
        PROVIDER_FI_API_KEY         = self.api_key
      }
    }

    # Override fallback's shared_service with FI-specific value
    provider "shared_service" {
      api_key = "fi-specific-key"

      export {
        SHARED_SERVICE_API_KEY = self.api_key
      }
    }
  }
}

# Using 'namespace' keyword for namespace-based config
namespace "SE" {
  environment "production" {
    provider "provider_se" {
      customer_number = "67890-SE"
      api_key         = "se-provider-key"

      export {
        PROVIDER_SE_CUSTOMER_NUMBER = self.customer_number
        PROVIDER_SE_API_KEY         = self.api_key
      }
    }
  }
}
```

### Loading Partition Exports

```php
use Cline\Huckle\HuckleManager;

$manager = resolve(HuckleManager::class);

// Load exports for SE partition (gets fallback + SE-specific)
$exports = $manager->loadEnv('path/to/config.hcl', [
    'partition' => 'SE',  // or 'division' for backwards compat
    'environment' => 'production',
]);

// Result includes:
// - SERVICE_A_KEY (from fallback)
// - SERVICE_A_SECRET (from fallback)
// - SERVICE_B_API_KEY (from fallback)
// - PROVIDER_SE_CUSTOMER_NUMBER (from SE partition)
// - PROVIDER_SE_API_KEY (from SE partition)
```

### Fallback Override Semantics

1. **Fallback loads first** - provides base layer of exports
2. **Partition loads second** - adds partition-specific exports
3. **Partition overrides fallback** - if both define the same export key, partition wins

```php
// FI overrides SHARED_SERVICE_API_KEY from fallback
$exports = $manager->loadEnv('path/to/config.hcl', [
    'partition' => 'FI',
    'environment' => 'production',
]);

// SHARED_SERVICE_API_KEY = "fi-specific-key" (from FI, not fallback)
// SERVICE_A_KEY = "pk_live_default" (from fallback, FI didn't override)
```

### Non-Matching Partition Context

When the requested partition doesn't exist, only fallback exports are returned:

```php
// NO partition doesn't exist - only fallback exports returned
$exports = $manager->loadEnv('path/to/config.hcl', [
    'partition' => 'NO',
    'environment' => 'production',
]);

// Only fallback exports: SERVICE_A_KEY, SERVICE_A_SECRET, SERVICE_B_API_KEY
```
