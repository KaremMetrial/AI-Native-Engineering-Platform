<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Discovery\Domain\Assumption;
use App\Discovery\Domain\Constraint;
use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\Question;
use App\Discovery\Domain\Response;
use App\Discovery\Infrastructure\EloquentAssumptionRepository;
use App\Discovery\Infrastructure\EloquentConstraintRepository;
use App\Discovery\Infrastructure\EloquentDiscoverySessionRepository;
use App\Discovery\Infrastructure\EloquentQuestionRepository;
use App\Discovery\Infrastructure\EloquentResponseRepository;
use Illuminate\Support\Str;

/**
 * Shared fixture builders for Discovery module tests. Reuses
 * CreatesGraphFixtures for tenant/user/project, since a DiscoverySession
 * structurally requires a project (FK to the shared kernel).
 */
trait CreatesDiscoveryFixtures
{
    use CreatesGraphFixtures;

    private function createDiscoverySession(string $tenantId, string $projectId, string $createdBy): string
    {
        $session = DiscoverySession::start((string) Str::uuid(), $tenantId, $projectId, 'Acme kickoff', $createdBy);
        (new EloquentDiscoverySessionRepository)->save($session);

        return $session->id;
    }

    private function createDiscoveryQuestion(string $tenantId, string $sessionId, string $createdBy, int $sequence = 1): string
    {
        $question = Question::ask((string) Str::uuid(), $tenantId, $sessionId, 'What problem are we solving?', $sequence, $createdBy);
        (new EloquentQuestionRepository)->save($question);

        return $question->id;
    }

    private function createDiscoveryResponse(string $tenantId, string $questionId, string $respondedBy): string
    {
        $response = Response::record((string) Str::uuid(), $tenantId, $questionId, 'Users need faster checkout.', $respondedBy);
        (new EloquentResponseRepository)->save($response);

        return $response->id;
    }

    private function createDiscoveryAssumption(string $tenantId, string $sessionId, string $createdBy): string
    {
        $assumption = Assumption::capture((string) Str::uuid(), $tenantId, $sessionId, 'Users have a stored payment method.', $createdBy);
        (new EloquentAssumptionRepository)->save($assumption);

        return $assumption->id;
    }

    private function createDiscoveryConstraint(string $tenantId, string $sessionId, string $createdBy): string
    {
        $constraint = Constraint::capture((string) Str::uuid(), $tenantId, $sessionId, 'Must comply with PCI DSS.', $createdBy);
        (new EloquentConstraintRepository)->save($constraint);

        return $constraint->id;
    }
}
