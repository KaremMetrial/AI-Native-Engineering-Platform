"""AI Orchestration Service entrypoint.

Read-only with respect to platform data; authoritative for nothing. Every
model call is mediated by the gateway (docs/architecture/ai/51-ai-gateway.md).
No workflow logic lives here yet -- this is the Phase 0 scaffold boot surface.
"""

from fastapi import FastAPI

app = FastAPI(
    title="AI Orchestration Service",
    version="0.1.0",
)


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}
