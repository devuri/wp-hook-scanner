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

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * WordPress Hook Scanner.
 *
 * Scans PHP files for WordPress hooks and displays them in a categorized format.
 */
final class HookScanner
{
    private const PATTERNS = [
        'add_action'    => '/\badd_action\s*\(\s*[\'"]([^\'"]+)[\'"]/m',
        'do_action'     => '/\bdo_action\s*\(\s*[\'"]([^\'"]+)[\'"]/m',
        'add_filter'    => '/\badd_filter\s*\(\s*[\'"]([^\'"]+)[\'"]/m',
        'apply_filters' => '/\bapply_filters\s*\(\s*[\'"]([^\'"]+)[\'"]/m',
    ];

    private const COLORS = [
        'green'   => "\033[32m",
        'yellow'  => "\033[33m",
        'blue'    => "\033[34m",
        'magenta' => "\033[35m",
        'cyan'    => "\033[36m",
        'white'   => "\033[37m",
        'gray'    => "\033[90m",
        'bold'    => "\033[1m",
        'reset'   => "\033[0m",
    ];

    private const LABELS = [
        'add_action'    => ['label' => 'Registered Actions', 'color' => 'green',   'symbol' => '▶'],
        'do_action'     => ['label' => 'Fired Actions',      'color' => 'yellow',  'symbol' => '⚡'],
        'add_filter'    => ['label' => 'Registered Filters', 'color' => 'blue',    'symbol' => '◆'],
        'apply_filters' => ['label' => 'Applied Filters',    'color' => 'magenta', 'symbol' => '✦'],
    ];

    /**
     * @var array<string, array<string, array{file: string, line: int}[]>>
     */
    private array $hooks = [];

    private bool $useColors;

    public function __construct(bool $useColors = true)
    {
        $this->useColors = $useColors && $this->isTty();

        foreach (array_keys(self::PATTERNS) as $type) {
            $this->hooks[$type] = [];
        }
    }

    /**
     * Scan a directory for WordPress hooks.
     *
     * @throws RuntimeException If the directory does not exist.
     */
    public function scan(string $directory): self
    {
        if ( ! is_dir($directory)) {
            throw new RuntimeException("Directory not found: {$directory}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $this->scanFile($file->getPathname());
        }

        return $this;
    }

    /**
     * Render the scan results to stdout.
     */
    public function render(): void
    {
        $this->printHeader();

        $totalHooks = 0;
        $stats = [];

        foreach (self::LABELS as $type => $config) {
            $hooks = $this->hooks[$type];
            $count = \count($hooks);
            $stats[$type] = $count;
            $totalHooks += $count;

            if (0 === $count) {
                continue;
            }

            $this->printSection($config['label'], $config['color'], $config['symbol'], $hooks);
        }

        $this->printSummary($stats, $totalHooks);
    }

    /**
     * Get hooks as an array.
     *
     * @return array<string, array<string, array{file: string, line: int}[]>>
     */
    public function toArray(): array
    {
        return $this->hooks;
    }

    /**
     * Get hooks as JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->hooks, JSON_PRETTY_PRINT) ?: '{}';
    }

    /**
     * Generate a normalized snapshot for comparison.
     * Only includes hook names (not line numbers) since lines change frequently.
     *
     * @return array<string, string[]>
     */
    public function toSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->hooks as $type => $hooks) {
            $hookNames = array_keys($hooks);
            sort($hookNames);
            $snapshot[$type] = $hookNames;
        }

        return $snapshot;
    }

    /**
     * Save the current snapshot to a file.
     */
    public function saveSnapshot(string $path): bool
    {
        $snapshot = $this->toSnapshot();
        $json = (json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') . "\n";

        return false !== file_put_contents($path, $json);
    }

    /**
     * Load a snapshot from a file.
     *
     * @return null|array<string, string[]>
     */
    public static function loadSnapshot(string $path): ?array
    {
        if ( ! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (false === $content) {
            return null;
        }

        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * Compare current hooks against a snapshot.
     *
     * @param array<string, string[]> $snapshot
     *
     * @return array{added: array<string, string[]>, removed: array<string, string[]>, match: bool}
     */
    public function compareToSnapshot(array $snapshot): array
    {
        $current = $this->toSnapshot();
        $added = [];
        $removed = [];

        foreach (array_keys(self::PATTERNS) as $type) {
            $currentHooks = $current[$type] ?? [];
            $snapshotHooks = $snapshot[$type] ?? [];

            $addedInType = array_diff($currentHooks, $snapshotHooks);
            $removedInType = array_diff($snapshotHooks, $currentHooks);

            if ( ! empty($addedInType)) {
                $added[$type] = array_values($addedInType);
            }
            if ( ! empty($removedInType)) {
                $removed[$type] = array_values($removedInType);
            }
        }

        return [
            'added'   => $added,
            'removed' => $removed,
            'match'   => empty($added) && empty($removed),
        ];
    }

    /**
     * Render a diff comparison to stdout.
     *
     * @param array{added: array<string, string[]>, removed: array<string, string[]>, match: bool} $diff
     */
    public function renderDiff(array $diff): void
    {
        if (($diff['match'] ?? false) === true) {
            echo $this->color("\n  ✓ Hooks match snapshot\n\n", 'green');

            return;
        }

        echo "\n";
        echo $this->color("  ✗ Hook snapshot mismatch\n", 'yellow');
        echo $this->color('  ' . str_repeat('═', 50) . "\n\n", 'gray');

        if ( ! empty($diff['added'])) {
            echo $this->color("  Added hooks (not in snapshot):\n", 'green');
            foreach ($diff['added'] as $type => $hooks) {
                $label = self::LABELS[$type]['label'] ?? $type;
                echo $this->color("    {$label}:\n", 'gray');
                foreach ($hooks as $hook) {
                    echo $this->color('      + ', 'green');
                    echo $this->color("{$hook}\n", 'white');
                }
            }
            echo "\n";
        }

        if ( ! empty($diff['removed'])) {
            echo $this->color("  Removed hooks (missing from code):\n", 'yellow');
            foreach ($diff['removed'] as $type => $hooks) {
                $label = self::LABELS[$type]['label'] ?? $type;
                echo $this->color("    {$label}:\n", 'gray');
                foreach ($hooks as $hook) {
                    echo $this->color('      - ', 'yellow');
                    echo $this->color("{$hook}\n", 'white');
                }
            }
            echo "\n";
        }

        echo $this->color("  Run with --update to update the snapshot\n\n", 'gray');
    }

    /**
     * Get the total count of unique hooks found.
     */
    public function getTotalCount(): int
    {
        $total = 0;
        foreach ($this->hooks as $hooks) {
            $total += \count($hooks);
        }

        return $total;
    }

    /**
     * Get hooks by type.
     *
     * @return array<string, array{file: string, line: int}[]>
     */
    public function getHooksByType(string $type): array
    {
        return $this->hooks[$type] ?? [];
    }

    /**
     * Check if a specific hook exists.
     */
    public function hasHook(string $hookName, ?string $type = null): bool
    {
        if (null !== $type) {
            return isset($this->hooks[$type][$hookName]);
        }

        foreach ($this->hooks as $hooks) {
            if (isset($hooks[$hookName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan a single file for hooks.
     */
    private function scanFile(string $filepath): void
    {
        $content = file_get_contents($filepath);
        if (false === $content) {
            return;
        }

        foreach (self::PATTERNS as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $hookName = $match[0];
                    $lineNumber = substr_count(substr($content, 0, $match[1]), "\n") + 1;

                    $this->hooks[$type][$hookName][] = [
                        'file' => $filepath,
                        'line' => $lineNumber,
                    ];
                }
            }
        }
    }

    // ----------------- Output helpers -----------------

    private function printHeader(): void
    {
        echo "\n";
        echo $this->color('  Hook Scanner', 'bold');
        echo $this->color(" v1.0\n", 'gray');
        echo $this->color('  ' . str_repeat('═', 50) . "\n\n", 'gray');
    }

    /**
     * @param array<string, array{file: string, line: int}[]> $hooks
     */
    private function printSection(string $label, string $color, string $symbol, array $hooks): void
    {
        ksort($hooks);
        $count = \count($hooks);

        echo $this->color("  {$symbol} {$label}", $color);
        echo $this->color(" ({$count})\n", 'gray');
        echo $this->color('  ' . str_repeat('─', 48) . "\n", 'gray');

        foreach ($hooks as $hookName => $locations) {
            echo $this->color('    ✓ ', 'green');
            echo $this->color($hookName, 'white');

            if (1 === \count($locations)) {
                $loc = $locations[0];
                echo $this->color(' → ', 'gray');
                echo $this->color($this->shortenPath($loc['file']), 'cyan');
                echo $this->color(":{$loc['line']}", 'gray');
            } else {
                echo $this->color(' (' . \count($locations) . ' occurrences)', 'gray');
            }
            echo "\n";
        }
        echo "\n";
    }

    /**
     * @param array<string, int> $stats
     */
    private function printSummary(array $stats, int $total): void
    {
        echo $this->color('  ' . str_repeat('═', 50) . "\n", 'gray');
        echo $this->color('  Summary: ', 'bold');
        echo $this->color("{$total} unique hooks found\n", 'white');

        $parts = [];
        foreach (self::LABELS as $type => $config) {
            if (($stats[$type] ?? 0) > 0) {
                $parts[] = $this->color((string) $stats[$type] . ' ', $config['color'])
                    . $this->color(strtolower($config['label']), 'gray');
            }
        }

        if ( ! empty($parts)) {
            echo '  ' . implode($this->color(' · ', 'gray'), $parts) . "\n";
        }

        echo "\n";
    }

    private function shortenPath(string $path): string
    {
        $cwd = getcwd() ?: '';
        if ('' !== $cwd && str_starts_with($path, $cwd)) {
            return ltrim(substr($path, \strlen($cwd)), '/');
        }

        return $path;
    }

    private function color(string $text, string $color): string
    {
        if ( ! $this->useColors || ! isset(self::COLORS[$color])) {
            return $text;
        }

        return self::COLORS[$color] . $text . self::COLORS['reset'];
    }

    private function isTty(): bool
    {
        return \function_exists('stream_isatty') ? @stream_isatty(STDOUT) : false;
    }
}
