<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Outbound port for issuing an API access token for an authenticated user.
 * Implemented in Infrastructure by wrapping Sanctum (framework-specific),
 * so the Application layer's login use case depends only on this contract.
 */
interface TokenIssuer
{
    public function issue(User $user): string;
}
