<?php

declare(strict_types=1);

namespace App\Graph\Domain;

/**
 * Value object on ArtifactVersion (docs/architecture/design/32-domain-model-and-ddd.md:
 * "no independent identity or lifecycle of its own, is immutable with the
 * version it describes, and is meaningless apart from it"). Records how a
 * version came to exist: which AI model and prompt version produced it
 * (if any), which other versions it was derived from, and token/cost for
 * the platform's cost-attribution source of truth (GenerationRecord,
 * docs/architecture/data/42-entity-model-and-ownership.md C11).
 *
 * Phase 1 creates versions through the human-authored path only (P-9: no
 * AI inference in a synchronous request path) -- human() is what's
 * actually used today. The general constructor exists for AI
 * orchestration to populate once that lands, not speculatively ahead of
 * it.
 */
final class Lineage
{
    /**
     * @param  list<string>  $inputVersionIds
     */
    public function __construct(
        public readonly ?string $model,
        public readonly ?string $promptVersion,
        public readonly array $inputVersionIds,
        public readonly ?int $tokens,
        public readonly ?float $cost,
    ) {}

    public static function human(): self
    {
        return new self(model: null, promptVersion: null, inputVersionIds: [], tokens: null, cost: null);
    }

    public function isAiGenerated(): bool
    {
        return $this->model !== null;
    }
}
