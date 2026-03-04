# Nested Hierarchy Nodes

Load nodes dynamically based on context (division, environment, provider, country) using hierarchical HCL blocks.

**Use case:** Managing service provider nodes across multiple divisions, environments, and region-specific configurations.

## The Problem

You have environment variables with suffixes like:
- `SERVICE_A_API_KEY_FI`, `SERVICE_A_API_KEY_SE`, `SERVICE_A_API_KEY_EE`
- `SERVICE_B_CUSTOMER_NUMBER_EE`, `SERVICE_B_CUSTOMER_NUMBER_LV`, `SERVICE_B_CUSTOMER_NUMBER_LT`

This becomes unwieldy with multiple divisions, environments, and providers.

## The Solution

Use nested hierarchy blocks with `Huckle::loadEnv()` to load context-specific nodes:

```php
use Cline\Huckle\Facades\Huckle;

// Load nodes for FI division, production environment, provider_a, region EE
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_a',
    'country' => 'EE',
]);

// Now access standard env vars without suffixes
$username = env('PROVIDER_A_USERNAME');         // 'provider-a-fi-prod-user'
$customerNumber = env('PROVIDER_A_CUSTOMER_NUMBER'); // 'provider-a-ee-customer'
```

## Hierarchy Structure

```
division > environment > provider > country > service
```

Additional block types supported for organizational hierarchies:
```
organization > team > user
```

Each level can have fields, exports, and nested children. Exports accumulate from parent to child, with deeper levels overriding same-named keys.

## Creating the Nodes File

```hcl
# nodes.hcl

division "FI" {
  environment "production" {
    provider "provider_a" {
      username = "provider-a-fi-prod-user"
      password = sensitive("provider-a-fi-prod-pass")

      export {
        PROVIDER_A_USERNAME = self.username
        PROVIDER_A_PASSWORD = self.password
      }

      country "EE" {
        customer_number = "provider-a-ee-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
      }

      country "LV" {
        customer_number = "provider-a-lv-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
      }

      country "LT" {
        customer_number = "provider-a-lt-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
      }
    }

    provider "provider_b" {
      api_key = "provider-b-fi-prod-key"
      customer_number = "provider-b-fi-prod-customer"

      export {
        PROVIDER_B_API_KEY = self.api_key
        PROVIDER_B_CUSTOMER_NUMBER = self.customer_number
      }
    }

    provider "provider_c" {
      username = "provider-c-global-user"
      password = sensitive("provider-c-global-pass")

      export {
        PROVIDER_C_USERNAME = self.username
        PROVIDER_C_PASSWORD = self.password
      }

      country "EE" {
        bearer_token = "provider-c-ee-token"
        base_url = "https://api.example.com/ee"

        export {
          PROVIDER_C_BEARER_TOKEN = self.bearer_token
          PROVIDER_C_BASE_URL = self.base_url
        }
      }

      country "LT" {
        bearer_token = "provider-c-lt-token"
        base_url = "https://api.example.com/lt"

        export {
          PROVIDER_C_BEARER_TOKEN = self.bearer_token
          PROVIDER_C_BASE_URL = self.base_url
        }
      }
    }
  }

  environment "staging" {
    provider "provider_a" {
      username = "provider-a-fi-staging-user"
      password = sensitive("provider-a-fi-staging-pass")

      export {
        PROVIDER_A_USERNAME = self.username
        PROVIDER_A_PASSWORD = self.password
      }
    }

    provider "provider_b" {
      api_key = "provider-b-fi-staging-key"
      customer_number = "provider-b-fi-staging-customer"

      export {
        PROVIDER_B_API_KEY = self.api_key
        PROVIDER_B_CUSTOMER_NUMBER = self.customer_number
      }
    }
  }
}

division "SE" {
  environment "production" {
    provider "provider_b" {
      api_key = "provider-b-se-prod-key"
      customer_number = "provider-b-se-prod-customer"

      export {
        PROVIDER_B_API_KEY = self.api_key
        PROVIDER_B_CUSTOMER_NUMBER = self.customer_number
      }
    }

    provider "provider_d" {
      api_uid = "provider-d-se-prod-uid"
      api_key = "provider-d-se-prod-key"
      customer_number = "provider-d-se-prod-customer"

      export {
        PROVIDER_D_API_UID = self.api_uid
        PROVIDER_D_API_KEY = self.api_key
        PROVIDER_D_CUSTOMER_NUMBER = self.customer_number
      }
    }
  }
}
```

## Loading Context-Specific Nodes

### Provider-Level Only

```php
// Load provider_b nodes for FI production (no country-specific config needed)
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_b',
]);

// Sets:
// PROVIDER_B_API_KEY = 'provider-b-fi-prod-key'
// PROVIDER_B_CUSTOMER_NUMBER = 'provider-b-fi-prod-customer'
```

### With Country Context

```php
// Load provider_a nodes for FI production, region EE
$exports = Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_a',
    'country' => 'EE',
]);

// Sets and returns:
// [
//     'PROVIDER_A_USERNAME' => 'provider-a-fi-prod-user',
//     'PROVIDER_A_PASSWORD' => 'provider-a-fi-prod-pass',
//     'PROVIDER_A_CUSTOMER_NUMBER' => 'provider-a-ee-customer',
// ]
```

### Switching Countries

```php
// Region LV
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_a',
    'country' => 'LV',
]);
// PROVIDER_A_CUSTOMER_NUMBER = 'provider-a-lv-customer'

// Region LT
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_a',
    'country' => 'LT',
]);
// PROVIDER_A_CUSTOMER_NUMBER = 'provider-a-lt-customer'
```

## Export Accumulation

Exports accumulate from parent levels to children. Deeper levels override same-named keys:

```php
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_c',
    'country' => 'EE',
]);

// Accumulates from provider + country:
// PROVIDER_C_USERNAME = 'provider-c-global-user'    (from provider)
// PROVIDER_C_PASSWORD = 'provider-c-global-pass'    (from provider)
// PROVIDER_C_BEARER_TOKEN = 'provider-c-ee-token'   (from country)
// PROVIDER_C_BASE_URL = 'https://api.example.com/ee'(from country)
```

## Using in Laravel Service Provider

```php
// AppServiceProvider.php
public function boot(): void
{
    // Load nodes based on application context
    $division = config('app.division', 'FI');
    $environment = config('app.env') === 'production' ? 'production' : 'staging';

    Huckle::loadEnv(base_path('nodes.hcl'), [
        'division' => $division,
        'environment' => $environment,
    ]);
}
```

## Using with a Service

```php
class ExternalApiService
{
    public function createRequest(string $targetRegion): void
    {
        // Load nodes for the target region
        Huckle::loadEnv(base_path('nodes.hcl'), [
            'division' => config('app.division'),
            'environment' => app()->environment(),
            'provider' => 'provider_a',
            'country' => $targetRegion,
        ]);

        // Now use standard env vars
        $client = new ApiClient(
            username: env('PROVIDER_A_USERNAME'),
            password: env('PROVIDER_A_PASSWORD'),
            customerNumber: env('PROVIDER_A_CUSTOMER_NUMBER'),
        );

        // Make request...
    }
}
```

## Querying Without Loading

Use `exportsForContext()` to get exports without setting environment variables:

```php
use Cline\Huckle\Facades\Huckle;

$parser = new \Cline\Huckle\Parser\HuckleParser();
$config = $parser->parseFile(base_path('nodes.hcl'));

$exports = $config->exportsForContext([
    'division' => 'SE',
    'environment' => 'production',
    'provider' => 'provider_d',
]);

// $exports = [
//     'PROVIDER_D_API_UID' => 'provider-d-se-prod-uid',
//     'PROVIDER_D_API_KEY' => 'provider-d-se-prod-key',
//     'PROVIDER_D_CUSTOMER_NUMBER' => 'provider-d-se-prod-customer',
// ]
```

## Context Matching Rules

- If context has `division`, only matching division is processed
- If context has `environment`, only matching environment is processed
- If context has `provider`, only matching provider is processed
- If context has `country`, only matching country is processed
- Missing context keys match all blocks at that level

```php
// No provider specified = exports from ALL providers in FI/production
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
]);

// Non-existent division = empty exports
Huckle::loadEnv('nodes.hcl', [
    'division' => 'NONEXISTENT',
]);
// Returns []
```

## Supported Block Types

The following block types are supported for building hierarchies:

**Organizational:**
- `division` - Top-level organizational unit
- `organization` - Alternative top-level for org structures
- `team` - Team-level grouping
- `user` - User-level configuration
- `tenant` - Multi-tenant SaaS scenarios
- `client` - Per-client/customer nodes

**Geographic (with validation):**
- `continent` - Continental grouping, validated values: `europe`, `asia`, `africa`, `north_america`, `south_america`, `oceania`, `antarctica`
- `zone` - Trading/economic zone, validated values: `eu`, `eea`, `efta`, `schengen`, `eurozone`, `usmca`, `mercosur`, `caricom`, `asean`, `rcep`, `cptpp`, `gcc`, `saarc`, `afcfta`, `ecowas`, `sadc`, `comesa`, `eac`, `au`
- `country` - Country-specific configuration, validated against ISO 3166-1 (alpha-2: `FI`, `SE` or alpha-3: `FIN`, `SWE`)
- `state` - State/province-level configuration, validated against ISO 3166-2 (full: `US-CA` or short: `CA` when nested in country block)

**Geographic (no validation):**
- `region` - Regional grouping (e.g., "nordic", "baltic", "apac") - flexible, no validation

**Infrastructure:**
- `environment` - Environment (production, staging, etc.)
- `provider` - Service provider or carrier
- `service` - Service type (express, economy, etc.)
- `carrier` - Carrier-specific configuration

## Geographic Validation

Geographic blocks (`continent`, `zone`, `country`, `state`) are validated by default. Invalid values throw a `ValidationException`:

```php
// This will throw ValidationException: Invalid country code 'INVALID'
$parser = new HuckleParser();
$config = $parser->parseFile('nodes.hcl'); // with country "INVALID" block
```

### Disabling Validation

To disable geographic validation (e.g., for testing or custom values):

```php
use Cline\Huckle\Parser\HuckleParser;

$parser = new HuckleParser();
$config = $parser->withoutGeoValidation()->parseFile('nodes.hcl');

// Or use the explicit method:
$config = $parser->withGeoValidation(false)->parseFile('nodes.hcl');
```

### Validation-Only Mode

To validate without building the full config:

```php
$parser = new HuckleParser();
$result = $parser->validateFile('nodes.hcl');

if (!$result['valid']) {
    foreach ($result['errors'] as $error) {
        echo $error . PHP_EOL;
    }
}
```
