division "FI" {
  environment "production" {
    provider "provider_a" {
      username = "provider-a-fi-prod-user"
      password = "provider-a-fi-prod-pass"

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
      password = "provider-c-global-pass"

      export {
        PROVIDER_C_USERNAME = self.username
        PROVIDER_C_PASSWORD = self.password
      }

      country "EE" {
        bearer_token = "provider-c-ee-token"
        base_url = "https://example.com/provider-c/ee"

        export {
          PROVIDER_C_BEARER_TOKEN = self.bearer_token
          PROVIDER_C_BASE_URL = self.base_url
        }
      }

      country "LT" {
        bearer_token = "provider-c-lt-token"
        base_url = "https://example.com/provider-c/lt"

        export {
          PROVIDER_C_BEARER_TOKEN = self.bearer_token
          PROVIDER_C_BASE_URL = self.base_url
        }
      }

      country "LV" {
        bearer_token = "provider-c-lv-token"
        base_url = "https://example.com/provider-c/lv"

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
      password = "provider-a-fi-staging-pass"

      export {
        PROVIDER_A_USERNAME = self.username
        PROVIDER_A_PASSWORD = self.password
      }

      country "EE" {
        customer_number = "provider-a-ee-staging-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
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

division "EE" {
  environment "production" {
    provider "provider_a" {
      username = "provider-a-ee-prod-user"
      password = "provider-a-ee-prod-pass"

      export {
        PROVIDER_A_USERNAME = self.username
        PROVIDER_A_PASSWORD = self.password
      }

      country "EE" {
        customer_number = "provider-a-ee-domestic-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
      }

      country "LV" {
        customer_number = "provider-a-ee-to-lv-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
      }
    }
  }
}
