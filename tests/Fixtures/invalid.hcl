# Invalid HCL - missing closing brace
group "database" "production" {
  credential "main" {
    host = "example.com"
  # Missing closing brace
