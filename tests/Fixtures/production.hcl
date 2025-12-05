# Environment-specific configuration for production

group "database" "production" {
  credential "env_specific" {
    host     = "env-specific.prod.internal"
    port     = 5432
    username = "env_user"
    password = sensitive("env123")

    export {
      DB_HOST = self.host
    }
  }
}
