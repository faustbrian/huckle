# Division hierarchy for testing
# Uses: division > environment > provider structure

division "FI" {
  environment "production" {
    provider "service_a" {
      customer_number = "12345-FI"
      api_key = "fi-secret-key"

      export {
        SERVICE_A_CUSTOMER_NUMBER = self.customer_number
        SERVICE_A_API_KEY = self.api_key
      }
    }
  }
}

division "SE" {
  environment "production" {
    provider "service_a" {
      customer_number = "67890-SE"
      api_key = "se-secret-key"

      export {
        SERVICE_A_CUSTOMER_NUMBER = self.customer_number
        SERVICE_A_API_KEY = self.api_key
      }
    }
  }
}

division "EE" {
  environment "production" {
    provider "service_a" {
      customer_number = "11111-EE"
      api_key = "ee-secret-key"

      export {
        SERVICE_A_CUSTOMER_NUMBER = self.customer_number
        SERVICE_A_API_KEY = self.api_key
      }
    }
  }
}
