<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Layer-dependency and boundary-direction rules (D-225) belong to Deptrac,
 * a purpose-built fitness-function tool (P11: don't re-implement what a
 * dedicated tool already does precisely) -- but Deptrac isn't installed in
 * this environment (D-206, tools/phpstan/README.md: the same GitHub-access
 * constraint that blocks Larastan). Until it is, the one layer rule that
 * actually has something to enforce (Domain must stay framework-free,
 * D-130) is covered directly in DomainLayerHasNoFrameworkDependencyTest.php
 * rather than left unchecked.
 *
 * This suite holds architectural rules that are awkward to express as a
 * layer dependency: things about *what* code contains, not *what it
 * imports*.
 */
class NoDebugStatementsTest extends TestCase
{
    private const FORBIDDEN = ['dd', 'dump', 'var_dump', 'print_r', 'ray'];

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
                // Word-boundary match, not plain substring: a naive
                // str_contains($contents, 'ray(') also matches is_array(,
                // in_array(, array( -- a real false positive this suite
                // hit against its own is_array() calls.
                if (preg_match('/(?<![a-zA-Z0-9_])'.preg_quote($needle, '/').'\s*\(/', $contents) === 1) {
                    $violations[] = $file->getPathname()." contains {$needle}(";
                }
            }
        }

        $this->assertSame([], $violations, "Debug statements found:\n".implode("\n", $violations));
    }
}
