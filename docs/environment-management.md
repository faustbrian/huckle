Organize nodes by environment (development, staging, production) and switch contexts dynamically.

**Use case:** Managing the same services across multiple environments with environment-specific configurations.

## Environment Structure

```hcl
partition "database" {
  environment "development" {
    provider "main" {
      host     = "localhost"
      port     = 5432
      username = "dev_user"
      password = sensitive("dev_password")
    }
  }

  environment "staging" {
    provider "main" {
      host     = "staging-db.internal"
      port     = 5432
      username = "staging_user"
      password = sensitive("staging_password")
    }
  }

  environment "production" {
    provider "main" {
      host     = "prod-db.internal"
      port     = 5432
      username = "prod_user"
      password = sensitive("prod_password")
    }
  }
}
```

## Filtering by Environment

### CLI

```bash
# List nodes in production
php artisan huckle:list --environment=production

# List nodes in multiple environments
php artisan huckle:list --environment=staging --environment=production
```

### Code

```php
use Cline\Huckle\Facades\Huckle;

// Get all nodes for an environment
$prodNodes = Huckle::forEnvironment('production');

// Export all production configs
Huckle::exportContextToEnv([
    'environment' => 'production',
]);
```

## Dynamic Environment Switching

```php
use Cline\Huckle\Facades\Huckle;

class DatabaseService
{
    public function getConfig(): array
    {
        $env = app()->environment();
        $node = Huckle::get("database.{$env}.main");

        return [
            'host' => $node->host,
            'port' => $node->port,
            'username' => $node->username,
            'password' => $node->password->reveal(),
        ];
    }
}
```

## Comparing Environments

```bash
# Compare production and staging
php artisan huckle:diff production staging

# Output shows differences in:
# - Node values
# - Missing nodes
# - Extra nodes
```

## Environment-Specific Tags

```hcl
partition "api" {
  environment "production" {
    tags = ["prod", "critical", "monitored"]

    provider "main" {
      # ...
    }
  }

  environment "development" {
    tags = ["dev", "local"]

    provider "main" {
      # ...
    }
  }
}
```

Filter by environment AND tag:

```bash
php artisan huckle:list --environment=production --tag=critical
```
