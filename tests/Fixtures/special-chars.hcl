# Test fixture for special characters in exported values

defaults {
  owner = "test-team"
}

group "test" "production" {
  credential "special" {
    host     = "test.internal"
    username = "user"
    password = sensitive("my pass#word")

    export {
      PASSWORD = self.password
    }
  }

  credential "spaces" {
    host     = "test.internal"
    username = "user name"
    password = sensitive("pass")

    export {
      USERNAME = self.username
    }
  }

  credential "quotes" {
    host     = "test.internal"
    username = "user"
    password = sensitive("pass\"with\"quotes")

    export {
      PASSWORD = self.password
    }
  }

  credential "single-quote" {
    host     = "test.internal"
    username = "user"
    password = sensitive("pass'with'quote")

    export {
      PASSWORD = self.password
    }
  }

  credential "hash" {
    host     = "test.internal"
    username = "user"
    password = sensitive("pass#word")

    export {
      PASSWORD = self.password
    }
  }
}
