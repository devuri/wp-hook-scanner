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
use WPHookScanner\Cli;

/**
 * Tests for the CLI class.
 *
 * @internal
 *
 * @coversNothing
 */
final class CliTest extends TestCase
{
    private string $fixturesDir;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__FILE__, 2) . '/fixtures';
        $this->tempDir = sys_get_temp_dir() . '/wp-hook-scanner-cli-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }
    }

    // =====================================================
    // Basic CLI Tests
    // =====================================================

    public function test_run_with_help_flag(): void
    {
        ob_start();
        $exitCode = Cli::run(['scan-hooks', '--help']);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('WP Hook Scanner', $output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('--json', $output);
    }

    public function test_run_with_version_flag(): void
    {
        ob_start();
        $exitCode = Cli::run(['scan-hooks', '--version']);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('WP Hook Scanner', $output);
        $this->assertStringContainsString('v1.0.0', $output);
    }

    public function test_run_with_json_flag(): void
    {
        ob_start();
        $exitCode = Cli::run(['scan-hooks', $this->fixturesDir, '--json']);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('add_action', $decoded);
    }

    public function test_run_default_pretty_print(): void
    {
        ob_start();
        $exitCode = Cli::run(['scan-hooks', $this->fixturesDir, '--no-color']);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Hook Scanner', $output);
        $this->assertStringContainsString('Summary:', $output);
    }

    // =====================================================
    // Snapshot CLI Tests
    // =====================================================

    public function test_run_with_update_flag(): void
    {
        $snapshotPath = $this->tempDir . '/test-snapshot.json';

        ob_start();
        $exitCode = Cli::run([
            'scan-hooks',
            $this->fixturesDir,
            '--update',
            '--snapshot=' . $snapshotPath,
        ]);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Snapshot saved', $output);
        $this->assertFileExists($snapshotPath);
    }

    public function test_run_with_check_flag_matching(): void
    {
        $snapshotPath = $this->tempDir . '/test-snapshot.json';

        // First create the snapshot (capture output to avoid risky test warning)
        ob_start();
        Cli::run([
            'scan-hooks',
            $this->fixturesDir,
            '--update',
            '--snapshot=' . $snapshotPath,
        ]);
        ob_end_clean();

        // Then check against it
        ob_start();
        $exitCode = Cli::run([
            'scan-hooks',
            $this->fixturesDir,
            '--check',
            '--snapshot=' . $snapshotPath,
            '--no-color',
        ]);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('match snapshot', $output);
    }

    public function test_run_with_check_flag_missing_snapshot(): void
    {
        $snapshotPath = $this->tempDir . '/nonexistent-snapshot.json';

        ob_start();
        $exitCode = Cli::run([
            'scan-hooks',
            $this->fixturesDir,
            '--check',
            '--snapshot=' . $snapshotPath,
        ]);
        ob_end_clean();

        $this->assertEquals(1, $exitCode);
    }

    // =====================================================
    // Error Handling Tests
    // =====================================================

    public function test_run_with_non_existent_directory(): void
    {
        ob_start();
        $exitCode = Cli::run(['scan-hooks', '/nonexistent/directory/path']);
        ob_end_clean();

        $this->assertEquals(1, $exitCode);
    }

    public function test_run_with_empty_directory(): void
    {
        ob_start();
        $exitCode = Cli::run(['scan-hooks', $this->tempDir, '--no-color']);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('0 unique hooks found', $output);
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
