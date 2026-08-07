# apps/ai

Python FastAPI AI orchestration service — the extracted AI service from
[`docs/architecture/05-high-level-architecture.md`](../../docs/architecture/05-high-level-architecture.md).
Owns workflow execution, context assembly, provider routing, guardrails,
evaluation, and cost metering
([`docs/architecture/ai/`](../../docs/architecture/ai/)). Never called
synchronously from a user-facing request path (P-9,
[`docs/architecture/04-non-functional-requirements.md`](../../docs/architecture/04-non-functional-requirements.md)) —
all AI work is queued.

## Commands

See the root [`README.md`](../../README.md#development) for the full,
CI-verified command set. Short version:

```bash
. .venv/bin/activate
ruff check .   # lint
mypy src       # strict typing
pytest         # tests
```

## Structure

```
src/
├── api/          FastAPI app and routes
├── providers/     Multi-provider adapters (Claude, GPT, Gemini, DeepSeek, ...)
├── prompts/       Prompt registry and versioning
├── retrieval/      Context assembly / grounding
├── workflows/     Workflow and bounded-agent execution
├── evaluation/    Evaluation harness
└── guardrails/    Injection containment, tool-call boundary enforcement
```

Each package currently holds only what `api/main.py`'s health check needs
— populated as the corresponding AI-architecture doc's design is
implemented, not speculatively ahead of it.
