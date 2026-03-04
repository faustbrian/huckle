# Adding Specsuite Tests

Add tests for new HashiCorp HCL specification files when the official spec is updated.

**Use case:** When HashiCorp releases new HCL specification tests, you want to ensure Huckle remains compliant by adding corresponding tests.

## Overview

The HCL specsuite uses two files per test case:
- `.hcl` - The HCL source to parse/validate
- `.t` - The expected outcome (result values or diagnostics)

## Step 1: Copy New Specsuite Files

Copy both `.hcl` and `.t` files from the official HashiCorp specsuite:

```bash
# From the hashicorp/hcl repository
# https://github.com/hashicorp/hcl/tree/main/hclsyntax/testdata

# Copy to appropriate location
cp new_feature.hcl tests/Fixtures/specsuite/expressions/
cp new_feature.t tests/Fixtures/specsuite/expressions/
```

## Step 2: Understand the .t File Format

The `.t` files are HCL themselves and can contain:

### Success Cases (No Errors Expected)

```hcl
# Result value expectation
result = {
  key = "value"
  number = 42
}

# Optional type specification
result_type = object({
  key = string
  number = number
})
```

### Error Cases (Diagnostics Expected)

```hcl
diagnostics {
  error {
    # Comment describing the error
    from {
      line   = 1
      column = 14
      byte   = 13
    }
    to {
      line   = 1
      column = 15
      byte   = 14
    }
  }
}
```

## Step 3: Parse the .t File

Use `TSpec::fromFile()` to read expectations:

```php
use Cline\Huckle\Testing\TSpec;

$tspec = TSpec::fromFile($tFilePath);

// Check what the spec expects
$tspec->expectsSuccess();     // true if no diagnostics
$tspec->expectsErrors();      // true if has error diagnostics
$tspec->expectedErrorCount(); // number of expected errors
$tspec->expectedErrors();     // array of ExpectedDiagnostic
$tspec->result;               // expected result value
$tspec->resultType;           // expected result type
```

## Step 4: Add Validator Tests

For syntax validation tests, add to `tests/Unit/HclValidatorTest.php`:

### Valid Syntax Test

```php
test('accepts new_feature per new_feature.t', function (): void {
    $hcl = file_get_contents(testFixture('specsuite/expressions/new_feature.hcl'));
    $tspec = TSpec::fromFile(testFixture('specsuite/expressions/new_feature.t'));

    $result = $this->validator->validate($hcl);

    expect($tspec->expectsSuccess())->toBeTrue();
    expect($result->isValid())->toBeTrue();
});
```

### Invalid Syntax Test (With Diagnostics)

```php
test('rejects invalid_construct per invalid_construct.t', function (): void {
    $hcl = file_get_contents(testFixture('specsuite/structure/invalid_construct.hcl'));
    $tspec = TSpec::fromFile(testFixture('specsuite/structure/invalid_construct.t'));

    $result = $this->validator->validate($hcl);

    expect($tspec->expectsErrors())->toBeTrue();
    expect($result->hasErrors())->toBeTrue();
    expect($result->errorCount())->toBeGreaterThanOrEqual($tspec->expectedErrorCount());

    // Optionally verify error locations match
    $expectedError = $tspec->expectedErrors()[0];
    $actualError = $result->errors()[0];

    expect($actualError->range->fromLine)->toBe($expectedError->range->fromLine);
    expect($actualError->range->fromColumn)->toBe($expectedError->range->fromColumn);
});
```

## Step 5: Add Parser Tests

For parsing/value tests, add to `tests/Unit/HclComplianceTest.php`:

```php
test('parses new_feature correctly', function (): void {
    $hcl = file_get_contents(testFixture('specsuite/expressions/new_feature.hcl'));
    $tspec = TSpec::fromFile(testFixture('specsuite/expressions/new_feature.t'));

    $result = Hcl::parse($hcl);

    // Compare against expected result from .t file
    expect($result)->toBe($tspec->result);
});
```

## Types of Specsuite Tests

### 1. Syntax Validation Tests
Test that the validator catches syntax errors.

Location: `tests/Unit/HclValidatorTest.php`

Examples:
- Single-line block violations
- Unclosed blocks
- Comma-separated attributes

### 2. Parser Compliance Tests
Test that the parser produces correct values.

Location: `tests/Unit/HclComplianceTest.php`

Examples:
- Primitive literals
- Operators
- Heredocs
- Comments

### 3. Schema Validation Tests
Some `.t` files test schema validation (not syntax). These should pass syntax validation but may fail schema validation.

```php
test('schema_test.t defines schema errors not syntax errors', function (): void {
    $tspec = TSpec::fromFile(testFixture('specsuite/schema_test.t'));

    // The .t expects errors, but they're schema errors
    expect($tspec->expectsErrors())->toBeTrue();

    // Our syntax validator should accept valid HCL syntax
    $hcl = file_get_contents(testFixture('specsuite/schema_test.hcl'));
    $result = $this->validator->validate($hcl);

    // Valid syntax, schema validation is application-specific
    expect($result->isValid())->toBeTrue();
});
```

## Quick Reference

| .t File Contains | Test Type | Test Location |
|------------------|-----------|---------------|
| `result = {...}` | Parser compliance | `HclComplianceTest.php` |
| `result_type = {...}` | Parser compliance | `HclComplianceTest.php` |
| `diagnostics { error {...} }` | Syntax validation | `HclValidatorTest.php` |
| Schema-level diagnostics | Document only | `HclValidatorTest.php` |

## Checklist for New Specsuite Files

- [ ] Copy `.hcl` file to `tests/Fixtures/specsuite/`
- [ ] Copy `.t` file to `tests/Fixtures/specsuite/`
- [ ] Determine test type (syntax vs parser vs schema)
- [ ] Add test using `TSpecParser` to read expectations
- [ ] Verify error locations if diagnostics expected
- [ ] Run `./vendor/bin/pest` to confirm tests pass
