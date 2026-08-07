<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Layer-dependency and boundary-direction rules (D-225) are enforced by
 * Deptrac (`vendor/bin/deptrac analyse`, deptrac.yaml at the repo root) --
 * a purpose-built fitness-function tool, not duplicated here (P11: don't
 * re-implement what a dedicated tool already does precisely).
 *
 * This suite holds architectural rules that are awkward to express as a
 * layer dependency: things about *what* code contains, not *what it
 * imports*.
 */
class NoDebugStatementsTest extends TestCase
{
    private const FORBIDDEN = ['dd(', 'dump(', 'var_dump(', 'print_r(', 'ray('];

    public function test_no_debug_statements_committed_in_app(): void
    {
        $appDir = dirname(__DIR__, 2).'/app';
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            foreach (self::FORBIDDEN as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = $file->getPathname()." contains {$needle}";
                }
            }
        }

        $this->assertSame([], $violations, "Debug statements found:\n".implode("\n", $violations));
    }
}
