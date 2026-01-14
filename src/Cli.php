<?php

declare(strict_types=1);

/*
 * This file is part of the WP Hook Scanner package.
 *
 * (c) Uriel Wilson
 *
 * The full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WPHookScanner;

use RuntimeException;

/**
 * CLI entry point for the Hook Scanner.
 */
final class Cli
{
    /**
     * Run the CLI application.
     *
     * @param array<int, string> $argv Command line arguments
     *
     * @return int Exit code (0 for success, non-zero for failure)
     */
    public static function run(array $argv): int
    {
        $args = \array_slice($argv, 1);

        $flags = array_values(array_filter($args, static fn (string $a): bool => str_starts_with($a, '--')));
        $positional = array_values(array_filter($args, static fn (string $a): bool => ! str_starts_with($a, '--')));

        $hasFlag = static fn (string $flag): bool => \in_array($flag, $flags, true);

        // Handle --help
        if ($hasFlag('--help') || $hasFlag('-h')) {
            self::printHelp();

            return 0;
        }

        // Handle --version
        if ($hasFlag('--version') || $hasFlag('-v')) {
            self::printVersion();

            return 0;
        }

        $directory = $positional[0] ?? 'src';
        $snapshotPath = self::getOptionValue($flags, '--snapshot') ?? '.hooks-snapshot.json';
        $useColors = ! $hasFlag('--no-color');

        try {
            $scanner = new HookScanner($useColors);
            $scanner->scan($directory);

            if ($hasFlag('--json')) {
                echo $scanner->toJson() . "\n";

                return 0;
            }

            if ($hasFlag('--update')) {
                if ($scanner->saveSnapshot($snapshotPath)) {
                    echo "\n  ✓ Snapshot saved to {$snapshotPath}\n\n";

                    return 0;
                }
                fwrite(STDERR, "Error: Failed to save snapshot\n");

                return 1;
            }

            if ($hasFlag('--check')) {
                $snapshot = HookScanner::loadSnapshot($snapshotPath);

                if (null === $snapshot) {
                    fwrite(STDERR, "Error: No snapshot found at {$snapshotPath}\n");
                    fwrite(STDERR, "Run with --update to create one\n");

                    return 1;
                }

                $diff = $scanner->compareToSnapshot($snapshot);
                $scanner->renderDiff($diff);

                return ($diff['match'] ?? false) ? 0 : 1;
            }

            // Default: pretty print
            $scanner->render();

            return 0;
        } catch (RuntimeException $e) {
            fwrite(STDERR, "Error: {$e->getMessage()}\n");

            return 1;
        }
    }

    /**
     * Extract option value from flags (e.g., --snapshot=path.json).
     *
     * @param array<int, string> $flags
     */
    private static function getOptionValue(array $flags, string $name): ?string
    {
        foreach ($flags as $f) {
            if (str_starts_with($f, $name . '=')) {
                return substr($f, \strlen($name) + 1);
            }
        }

        return null;
    }

    /**
     * Print help message.
     */
    private static function printHelp(): void
    {
        echo <<<'HELP'

  WP Hook Scanner - Scan PHP files for WordPress hooks

  Usage:
    scan-hooks [directory] [options]

  Arguments:
    directory              Directory to scan (default: src)

  Options:
    --json                 Output results as JSON
    --update               Create/update snapshot file
    --check                Compare against snapshot (for CI)
    --snapshot=<path>      Custom snapshot file path (default: .hooks-snapshot.json)
    --no-color             Disable colored output
    --help, -h             Show this help message
    --version, -v          Show version information

  Examples:
    scan-hooks src                          # Pretty print hooks in src/
    scan-hooks src --json                   # Output as JSON
    scan-hooks src --update                 # Create snapshot
    scan-hooks src --check                  # Compare against snapshot
    scan-hooks . --snapshot=hooks.json      # Use custom snapshot path


HELP;
    }

    /**
     * Print version information.
     */
    private static function printVersion(): void
    {
        echo "WP Hook Scanner v1.0.0\n";
    }
}
