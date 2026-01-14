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

namespace WPHookScanner\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPHookScanner\HookScanner;

/**
 * Tests for the HookScanner class.
 *
 * @internal
 *
 * @coversNothing
 */
final class HookScannerTest extends TestCase
{
    private string $fixturesDir;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__FILE__, 2) . '/fixtures';
        $this->tempDir = sys_get_temp_dir() . '/wp-hook-scanner-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }
    }

    // =====================================================
    // Basic Scanning Tests
    // =====================================================

    public function test_scan_finds_add_action_hooks(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $actions = $scanner->getHooksByType('add_action');

        $this->assertArrayHasKey('init', $actions);
        $this->assertArrayHasKey('wp_loaded', $actions);
        $this->assertArrayHasKey('admin_init', $actions);
        $this->assertArrayHasKey('wp_enqueue_scripts', $actions);
    }

    public function test_scan_finds_do_action_hooks(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $actions = $scanner->getHooksByType('do_action');

        $this->assertArrayHasKey('my_plugin_loaded', $actions);
        $this->assertArrayHasKey('my_custom_event', $actions);
    }

    public function test_scan_finds_add_filter_hooks(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $filters = $scanner->getHooksByType('add_filter');

        $this->assertArrayHasKey('the_content', $filters);
        $this->assertArrayHasKey('the_title', $filters);
        $this->assertArrayHasKey('body_class', $filters);
    }

    public function test_scan_finds_apply_filters_hooks(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $filters = $scanner->getHooksByType('apply_filters');

        $this->assertArrayHasKey('my_plugin_value', $filters);
        $this->assertArrayHasKey('my_custom_filter', $filters);
    }

    public function test_scan_tracks_multiple_occurrences(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $actions = $scanner->getHooksByType('add_action');
        $filters = $scanner->getHooksByType('apply_filters');

        // 'init' appears in both fixture files
        $this->assertCount(2, $actions['init']);

        // 'my_plugin_value' appears in both fixture files
        $this->assertCount(2, $filters['my_plugin_value']);
    }

    public function test_scan_tracks_file_and_line_number(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $actions = $scanner->getHooksByType('add_action');
        $location = $actions['admin_init'][0];

        $this->assertArrayHasKey('file', $location);
        $this->assertArrayHasKey('line', $location);
        $this->assertIsString($location['file']);
        $this->assertIsInt($location['line']);
        $this->assertGreaterThan(0, $location['line']);
    }

    // =====================================================
    // Error Handling Tests
    // =====================================================

    public function test_scan_throws_exception_for_non_existent_directory(): void
    {
        $scanner = new HookScanner(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Directory not found');

        $scanner->scan('/non/existent/directory');
    }

    public function test_scan_empty_directory(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->tempDir);

        $this->assertEquals(0, $scanner->getTotalCount());
    }

    // =====================================================
    // Output Format Tests
    // =====================================================

    public function test_to_array_returns_correct_structure(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $array = $scanner->toArray();

        $this->assertArrayHasKey('add_action', $array);
        $this->assertArrayHasKey('do_action', $array);
        $this->assertArrayHasKey('add_filter', $array);
        $this->assertArrayHasKey('apply_filters', $array);
    }

    public function test_to_json_returns_valid_json(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $json = $scanner->toJson();
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('add_action', $decoded);
    }

    public function test_to_json_on_empty_scanner(): void
    {
        $scanner = new HookScanner(false);
        $json = $scanner->toJson();

        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEmpty($decoded['add_action']);
    }

    // =====================================================
    // Snapshot Tests
    // =====================================================

    public function test_to_snapshot_returns_normalized_structure(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $snapshot = $scanner->toSnapshot();

        $this->assertArrayHasKey('add_action', $snapshot);
        $this->assertArrayHasKey('do_action', $snapshot);
        $this->assertArrayHasKey('add_filter', $snapshot);
        $this->assertArrayHasKey('apply_filters', $snapshot);

        // Snapshot should contain hook names only (as values), sorted
        $this->assertContains('init', $snapshot['add_action']);
        $this->assertContains('wp_loaded', $snapshot['add_action']);

        // Check that arrays are sorted
        $this->assertEquals(
            $snapshot['add_action'],
            array_values(array_unique($snapshot['add_action']))
        );
    }

    public function test_save_and_load_snapshot(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $snapshotPath = $this->tempDir . '/test-snapshot.json';

        // Save snapshot
        $saved = $scanner->saveSnapshot($snapshotPath);
        $this->assertTrue($saved);
        $this->assertFileExists($snapshotPath);

        // Load snapshot
        $loaded = HookScanner::loadSnapshot($snapshotPath);
        $this->assertNotNull($loaded);
        $this->assertEquals($scanner->toSnapshot(), $loaded);
    }

    public function test_load_snapshot_returns_null_for_non_existent(): void
    {
        $result = HookScanner::loadSnapshot('/non/existent/snapshot.json');
        $this->assertNull($result);
    }

    public function test_compare_to_snapshot_matches_identical(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $snapshot = $scanner->toSnapshot();
        $diff = $scanner->compareToSnapshot($snapshot);

        $this->assertTrue($diff['match']);
        $this->assertEmpty($diff['added']);
        $this->assertEmpty($diff['removed']);
    }

    public function test_compare_to_snapshot_detects_added_hooks(): void
    {
        // Create a snapshot with fewer hooks
        $oldSnapshot = [
            'add_action'    => ['init'],
            'do_action'     => [],
            'add_filter'    => [],
            'apply_filters' => [],
        ];

        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $diff = $scanner->compareToSnapshot($oldSnapshot);

        $this->assertFalse($diff['match']);
        $this->assertNotEmpty($diff['added']);
        $this->assertContains('wp_loaded', $diff['added']['add_action'] ?? []);
    }

    public function test_compare_to_snapshot_detects_removed_hooks(): void
    {
        // Create a snapshot with hooks that don't exist
        $oldSnapshot = [
            'add_action'    => ['init', 'nonexistent_hook', 'another_fake_hook'],
            'do_action'     => [],
            'add_filter'    => [],
            'apply_filters' => [],
        ];

        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $diff = $scanner->compareToSnapshot($oldSnapshot);

        $this->assertFalse($diff['match']);
        $this->assertNotEmpty($diff['removed']);
        $this->assertContains('nonexistent_hook', $diff['removed']['add_action'] ?? []);
        $this->assertContains('another_fake_hook', $diff['removed']['add_action'] ?? []);
    }

    // =====================================================
    // Helper Method Tests
    // =====================================================

    public function test_get_total_count(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $total = $scanner->getTotalCount();

        $this->assertGreaterThan(0, $total);
    }

    public function test_has_hook_with_type(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $this->assertTrue($scanner->hasHook('init', 'add_action'));
        $this->assertFalse($scanner->hasHook('init', 'add_filter'));
        $this->assertFalse($scanner->hasHook('nonexistent', 'add_action'));
    }

    public function test_has_hook_without_type(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $this->assertTrue($scanner->hasHook('init'));
        $this->assertTrue($scanner->hasHook('the_content'));
        $this->assertFalse($scanner->hasHook('completely_fake_hook'));
    }

    public function test_get_hooks_by_type_returns_empty_for_unknown_type(): void
    {
        $scanner = new HookScanner(false);
        $scanner->scan($this->fixturesDir);

        $hooks = $scanner->getHooksByType('unknown_type');

        $this->assertIsArray($hooks);
        $this->assertEmpty($hooks);
    }

    // =====================================================
    // Fluent Interface Test
    // =====================================================

    public function test_scan_returns_self(): void
    {
        $scanner = new HookScanner(false);
        $result = $scanner->scan($this->fixturesDir);

        $this->assertSame($scanner, $result);
    }

    // =====================================================
    // Non-PHP Files Test
    // =====================================================

    public function test_scan_ignores_non_php_files(): void
    {
        // Create a non-PHP file with hook-like content
        $jsFile = $this->tempDir . '/script.js';
        file_put_contents($jsFile, "add_action('fake_js_hook', callback);");

        $scanner = new HookScanner(false);
        $scanner->scan($this->tempDir);

        $this->assertEquals(0, $scanner->getTotalCount());
    }

    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
