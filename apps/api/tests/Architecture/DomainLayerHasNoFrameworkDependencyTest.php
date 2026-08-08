<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * D-130 / docs/delivery/11-repository-and-folder-strategy.md: "Domain
 * imports no framework code," enforced by "static analysis on namespace
 * prefixes." That analysis is Deptrac's job and Deptrac isn't installed
 * here (D-206) -- this is the honest stand-in: a real, if narrower, check
 * rather than an unenforced rule sitting only in documentation. Runs
 * against every module's Domain/ directory, not just Identity's, so the
 * next module gets this for free.
 */
class DomainLayerHasNoFrameworkDependencyTest extends TestCase
{
    private const FORBIDDEN_PREFIXES = ['Illuminate\\', 'Laravel\\', 'Symfony\\'];

    public function test_domain_layers_import_no_framework_namespaces(): void
    {
        $appDir = dirname(__DIR__, 2).'/app';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach (self::FORBIDDEN_PREFIXES as $prefix) {
                if (preg_match('/^use\s+'.preg_quote($prefix, '/').'/m', $contents) === 1) {
                    $violations[] = "{$file->getPathname()} imports the framework namespace [{$prefix}]";
                }
            }
        }

        $this->assertSame([], $violations, "Domain layer framework-free rule (D-130) violated:\n".implode("\n", $violations));
    }
}
