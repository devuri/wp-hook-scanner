# WP Hook Scanner

A PHP tool that scans your codebase for WordPress hooks (`add_action`, `do_action`, `add_filter`, `apply_filters`) and generates categorized reports.

Perfect for:
- **Documenting** hooks in your WordPress plugins and themes
- **CI/CD pipelines** to track hook changes between releases
- **Code audits** to understand hook dependencies

## Requirements

- PHP 8.1 or higher

## Installation

Install via Composer:

```bash
composer require --dev devuri/wp-hook-scanner
```

Or add to your `composer.json`:

```json
{
    "require-dev": {
        "devuri/wp-hook-scanner": "^1.0"
    }
}
```

## Usage

### Command Line

**Pretty output (default):**

```bash
vendor/bin/scan-hooks src
```

Output:
```
  Hook Scanner v1.0
  ══════════════════════════════════════════════════

  ▶ Registered Actions (3)
  ────────────────────────────────────────────────
    ✓ admin_init → src/Admin.php:45
    ✓ init → src/Plugin.php:23
    ✓ wp_loaded → src/Bootstrap.php:12

  ⚡ Fired Actions (2)
  ────────────────────────────────────────────────
    ✓ my_plugin_loaded → src/Plugin.php:30
    ✓ my_custom_event (2 occurrences)

  ══════════════════════════════════════════════════
  Summary: 5 unique hooks found
  3 registered actions · 2 fired actions
```

**JSON output:**

```bash
vendor/bin/scan-hooks src --json
```

**All options:**

```bash
vendor/bin/scan-hooks [directory] [options]

Options:
  --json                 Output results as JSON
  --update               Create/update snapshot file
  --check                Compare against snapshot (for CI)
  --snapshot=<path>      Custom snapshot file path (default: .hooks-snapshot.json)
  --no-color             Disable colored output
  --help, -h             Show help message
  --version, -v          Show version information
```

### Snapshot Mode (CI/CD)

Track hook changes across releases using snapshots:

**Create a snapshot:**

```bash
vendor/bin/scan-hooks src --update
```

This creates `.hooks-snapshot.json` containing all current hooks.

**Check against snapshot:**

```bash
vendor/bin/scan-hooks src --check
```

Returns exit code `0` if hooks match, `1` if there are differences.

**Custom snapshot path:**

```bash
vendor/bin/scan-hooks src --update --snapshot=hooks.json
vendor/bin/scan-hooks src --check --snapshot=hooks.json
```

### GitHub Actions Example

```yaml
name: Hook Check

on: [push, pull_request]

jobs:
  hooks:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          
      - name: Install dependencies
        run: composer install --no-progress
        
      - name: Check hooks
        run: vendor/bin/scan-hooks src --check
```

### Programmatic Usage

```php
use WPHookScanner\HookScanner;

$scanner = new HookScanner();
$scanner->scan('src');

// Get all hooks as array
$hooks = $scanner->toArray();

// Get hooks as JSON
$json = $scanner->toJson();

// Check for specific hook
if ($scanner->hasHook('my_custom_action', 'add_action')) {
    echo "Found it!";
}

// Get hooks by type
$actions = $scanner->getHooksByType('add_action');
$filters = $scanner->getHooksByType('apply_filters');

// Get total count
$total = $scanner->getTotalCount();

// Snapshot operations
$scanner->saveSnapshot('.hooks-snapshot.json');
$snapshot = HookScanner::loadSnapshot('.hooks-snapshot.json');
$diff = $scanner->compareToSnapshot($snapshot);
```

## Hook Types Detected

| Type | Description | Symbol |
|------|-------------|--------|
| `add_action` | Registered action hooks | ▶ |
| `do_action` | Fired/triggered actions | ⚡ |
| `add_filter` | Registered filter hooks | ◆ |
| `apply_filters` | Applied filter hooks | ✦ |

## Output Formats

### Pretty Print (Terminal)

Colorized, categorized output ideal for development.

### JSON

Machine-readable format for tooling integration:

```json
{
    "add_action": {
        "init": [
            {"file": "src/Plugin.php", "line": 23}
        ],
        "admin_init": [
            {"file": "src/Admin.php", "line": 45}
        ]
    },
    "do_action": { ... },
    "add_filter": { ... },
    "apply_filters": { ... }
}
```

### Snapshot

Normalized format for comparison (excludes line numbers):

```json
{
    "add_action": ["admin_init", "init", "wp_loaded"],
    "do_action": ["my_custom_event", "my_plugin_loaded"],
    "add_filter": ["the_content", "the_title"],
    "apply_filters": ["my_plugin_value"]
}
```

## Testing

Run the test suite:

```bash
composer test
```

Or directly:

```bash
vendor/bin/phpunit
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License. See [LICENSE](LICENSE) for details.
