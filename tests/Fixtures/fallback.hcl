# Fallback hierarchy for testing
# Fallback provides defaults that partitions can override

# Fallback block - provides default exports for all partitions
fallback {
  environment "production" {
    provider "service_a" {
      key = "pk_test_default"
      secret = "sk_test_default"

      export {
        SERVICE_A_KEY = self.key
        SERVICE_A_SECRET = self.secret
      }
    }

    provider "service_b" {
      api_key = "default-service-b-key"

      export {
        SERVICE_B_API_KEY = self.api_key
      }
    }

    provider "shared_service" {
      api_key = "shared-default-key"

      export {
        SHARED_SERVICE_API_KEY = self.api_key
      }
    }
  }

  environment "staging" "local" "sandbox" {
    provider "service_a" {
      key = "pk_test_staging"
      secret = "sk_test_staging"

      export {
        SERVICE_A_KEY = self.key
        SERVICE_A_SECRET = self.secret
      }
    }

    provider "service_b" {
      api_key = "staging-service-b-key"

      export {
        SERVICE_B_API_KEY = self.api_key
      }
    }
  }
}

# Tenant FI - overrides some values
tenant "FI" {
  environment "production" {
    provider "provider_fi" {
      customer_number = "12345-FI"
      api_key = "fi-provider-key"

      export {
        PROVIDER_FI_CUSTOMER_NUMBER = self.customer_number
        PROVIDER_FI_API_KEY = self.api_key
      }
    }

    # Override shared_service with FI-specific value
    provider "shared_service" {
      api_key = "fi-specific-key"

      export {
        SHARED_SERVICE_API_KEY = self.api_key
      }
    }
  }
}

# Tenant SE - uses fallback values for service_a/service_b
tenant "SE" {
  environment "production" {
    provider "provider_se" {
      customer_number = "67890-SE"
      api_key = "se-provider-key"

      export {
        PROVIDER_SE_CUSTOMER_NUMBER = self.customer_number
        PROVIDER_SE_API_KEY = self.api_key
      }
    }
  }
}
