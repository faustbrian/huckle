# Test fixture for config mappings and config:cache compatibility

# Global mappings: env variable name -> Laravel config path
mappings {
  STRIPE_KEY        = "cashier.key"
  STRIPE_SECRET     = "cashier.secret"
  REDIS_HOST        = "database.redis.default.host"
  REDIS_PASSWORD    = "database.redis.default.password"
}

partition "payments" {
  environment "production" {
    provider "stripe" {
      api_key    = sensitive("pk_live_xxx")
      api_secret = sensitive("sk_live_xxx")
      webhook_secret = sensitive("whsec_xxx")

      # Traditional env exports
      export {
        STRIPE_KEY        = self.api_key
        STRIPE_SECRET     = self.api_secret
        STRIPE_WEBHOOK_SECRET = self.webhook_secret
      }

      # Direct config path mappings (takes precedence over global mappings)
      config {
        "cashier.key"           = self.api_key
        "cashier.secret"        = self.api_secret
        "cashier.webhook.secret" = self.webhook_secret
        "services.stripe.key"   = self.api_key
      }
    }
  }

  environment "staging" {
    provider "stripe" {
      api_key    = sensitive("pk_test_xxx")
      api_secret = sensitive("sk_test_xxx")

      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }

      config {
        "cashier.key"    = self.api_key
        "cashier.secret" = self.api_secret
      }
    }
  }
}

partition "cache" {
  environment "production" {
    provider "redis" {
      host     = "redis.prod.internal"
      port     = 6379
      password = sensitive("redis_secret")

      export {
        REDIS_HOST     = self.host
        REDIS_PORT     = self.port
        REDIS_PASSWORD = self.password
      }

      # These override the global mappings for this specific node
      config {
        "database.redis.default.host"     = self.host
        "database.redis.default.port"     = self.port
        "database.redis.default.password" = self.password
      }
    }
  }
}
