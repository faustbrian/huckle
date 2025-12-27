---
title: Exports Syncing
description: Export node values to environment variables and sync them to your .env file.
---

Export node values to environment variables and sync them to your .env file.

**Use case:** Keeping your `.env` file in sync with your HCL configuration, especially during development.

## Defining Exports

Add `export` blocks inside nodes to define which values should be exported:

```hcl
partition "database" {
  environment "production" {
    provider "main" {
      host     = "db.prod.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("secret123")
      database = "myapp"

      export {
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
        DB_DATABASE = self.database
      }
    }
  }
}
```

## Exporting to Environment

### CLI

```bash
# Export specific node
php artisan huckle:export database.production.main

# Export all nodes
php artisan huckle:export --all
```

### Code

```php
use Cline\Huckle\Facades\Huckle;

// Export specific node
$node = Huckle::get('database.production.main');
$node->exportToEnv();

// Export by context
Huckle::exportContextToEnv([
    'partition' => 'database',
    'environment' => 'production',
]);
```

## Syncing to .env File

The `huckle:sync` command writes exported values directly to your `.env` file:

```bash
# Sync to default .env
php artisan huckle:sync

# Sync to specific file
php artisan huckle:sync --file=.env.production

# Preview changes without writing
php artisan huckle:sync --dry-run
```

## How Syncing Works

1. Reads the current `.env` file
2. Finds all exported values from your nodes
3. Updates existing keys or adds new ones
4. Preserves comments and formatting
5. Writes the updated file

## Example Workflow

```hcl
# nodes.hcl
partition "services" {
  environment "local" {
    provider "stripe" {
      api_key    = sensitive("pk_test_xxx")
      api_secret = sensitive("sk_test_xxx")

      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }
    }
  }
}
```

```bash
# Your .env before:
# APP_NAME=MyApp
# APP_ENV=local

php artisan huckle:sync

# Your .env after:
# APP_NAME=MyApp
# APP_ENV=local
# STRIPE_KEY=pk_test_xxx
# STRIPE_SECRET=sk_test_xxx
```

## Context-Based Export

Export only nodes matching specific criteria:

```php
use Cline\Huckle\Facades\Huckle;

// Export all nodes for a partition
Huckle::exportContextToEnv([
    'partition' => 'services',
]);

// Export all nodes for an environment
Huckle::exportContextToEnv([
    'environment' => 'production',
]);

// Combine filters
Huckle::exportContextToEnv([
    'partition' => 'database',
    'environment' => 'production',
    'provider' => 'main',
]);
```

## Viewing Exports

```bash
# Show what would be exported (table format)
php artisan huckle:lint --table

# Filter by context
php artisan huckle:lint --table --environment=production
php artisan huckle:lint --table --partition=database
```
