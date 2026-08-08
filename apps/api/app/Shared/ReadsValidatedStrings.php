<?php

declare(strict_types=1);

namespace App\Shared;

use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

/**
 * FormRequest::validated() resolves to `mixed` without Laravel-aware
 * static analysis stubs (the tracked Larastan gap, D-206,
 * tools/phpstan/README.md) -- this narrows the runtime shape explicitly
 * rather than casting mixed away.
 *
 * Lives in Shared, not any one module, because every module's
 * Presentation layer needs it and
 * docs/delivery/11-repository-and-folder-strategy.md makes Shared (with
 * Tenancy) the only place importable by all.
 */
trait ReadsValidatedStrings
{
    protected function stringField(FormRequest $request, string $key): string
    {
        $value = $request->validated($key);

        if (! is_string($value)) {
            throw new RuntimeException("Expected field [{$key}] to be a string.");
        }

        return $value;
    }
}
