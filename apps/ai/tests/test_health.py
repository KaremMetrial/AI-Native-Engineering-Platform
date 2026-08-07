"""Harness proof: the FastAPI app boots and responds. Extended per workflow as
capabilities land -- see docs/architecture/ai/56-workflow-and-agent-engine.md.
"""

from fastapi.testclient import TestClient

from src.api.main import app

client = TestClient(app)


def test_health_endpoint_returns_ok() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
