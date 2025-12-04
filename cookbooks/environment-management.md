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
php artisan huckle:export --env=production

# Export only staging credentials
php artisan huckle:export --env=staging --format=shell
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
