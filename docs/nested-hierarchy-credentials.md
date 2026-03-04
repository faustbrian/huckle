Organize credentials in deeply nested hierarchies for complex multi-tenant or multi-region setups.

**Use case:** Managing credentials across multiple countries, tenants, environments, and service providers.

## Hierarchy Structure

```hcl
partition "EU" {
  environment "production" {
    tags = ["eu", "prod", "gdpr"]

    provider "stripe" {
      country "DE" {
        api_key    = sensitive("pk_live_de_xxx")
        api_secret = sensitive("sk_live_de_xxx")
        webhook_secret = sensitive("whsec_de_xxx")
      }

      country "FR" {
        api_key    = sensitive("pk_live_fr_xxx")
        api_secret = sensitive("sk_live_fr_xxx")
        webhook_secret = sensitive("whsec_fr_xxx")
      }

      country "SE" {
        api_key    = sensitive("pk_live_se_xxx")
        api_secret = sensitive("sk_live_se_xxx")
        webhook_secret = sensitive("whsec_se_xxx")
      }
    }

    provider "database" {
      country "DE" {
        host     = "db-de.eu.prod.internal"
        port     = 5432
        username = "app_de"
        password = sensitive("de_prod_secret")
      }

      country "FR" {
        host     = "db-fr.eu.prod.internal"
        port     = 5432
        username = "app_fr"
        password = sensitive("fr_prod_secret")
      }
    }
  }
}
```

## Accessing Nested Nodes

```php
use Cline\Huckle\Facades\Huckle;

// Full path access
$deStripe = Huckle::get('EU.production.stripe.DE');
$frDb = Huckle::get('EU.production.database.FR');

// Access fields
$apiKey = $deStripe->api_key->reveal();  // 'pk_live_de_xxx'
$host = $frDb->host;  // 'db-fr.eu.prod.internal'
```

## Filtering by Country

```bash
# List all nodes for Germany
php artisan huckle:lint --table --country=DE

# List Stripe nodes for Sweden in production
php artisan huckle:lint --table --provider=stripe --country=SE --environment=production
```

## Context-Based Export

```php
use Cline\Huckle\Facades\Huckle;

// Export all German production configs
Huckle::exportContextToEnv([
    'partition' => 'EU',
    'environment' => 'production',
    'country' => 'DE',
]);

// Export all Stripe configs across EU
Huckle::exportContextToEnv([
    'partition' => 'EU',
    'provider' => 'stripe',
]);
```

## Multi-Region Setup

```hcl
# US region
partition "US" {
  environment "production" {
    provider "stripe" {
      country "US" {
        api_key = sensitive("pk_live_us_xxx")
      }
    }
  }
}

# EU region
partition "EU" {
  environment "production" {
    provider "stripe" {
      country "DE" {
        api_key = sensitive("pk_live_de_xxx")
      }
      country "FR" {
        api_key = sensitive("pk_live_fr_xxx")
      }
    }
  }
}

# APAC region
partition "APAC" {
  environment "production" {
    provider "stripe" {
      country "JP" {
        api_key = sensitive("pk_live_jp_xxx")
      }
      country "AU" {
        api_key = sensitive("pk_live_au_xxx")
      }
    }
  }
}
```

## Dynamic Country Selection

```php
use Cline\Huckle\Facades\Huckle;

class PaymentService
{
    public function getStripeKey(string $region, string $country): string
    {
        $node = Huckle::get("{$region}.production.stripe.{$country}");

        return $node->api_key->reveal();
    }
}

// Usage
$service = new PaymentService();
$key = $service->getStripeKey('EU', 'DE');  // German Stripe key
$key = $service->getStripeKey('US', 'US');  // US Stripe key
```

## Comparing Countries

```bash
# Compare German and French configs
php artisan huckle:diff EU.production.stripe.DE EU.production.stripe.FR
```
