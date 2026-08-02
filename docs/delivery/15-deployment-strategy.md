# Deployment Strategy

## Purpose

Define how code reaches production safely and frequently, how infrastructure is
managed, and how the platform is operated. Deployment frequency and reliability
are the practices that make trunk-based development (`12`) viable and keep
change small enough to be safe.

## Scope

**In scope:** CI/CD pipeline, deployment mechanics, infrastructure management,
environment topology, release and rollback, observability in operation,
incident readiness, and disaster recovery.

**Out of scope:** branching and environments-as-workflow (`12`), testing content
(`14`).

---

## Principles

1. **Deploy small, deploy often.** Deployment risk scales with change size, not
   frequency. Ten small deploys are safer than one large one, and each is
   trivially revertible.
2. **Every deploy is automated and identical across environments.** Manual
   steps are unrepeatable and unauditable; a runbook step performed by a tired
   engineer at 2am is the least reliable component in any system.
3. **Rollback is always available and always fast.** If we cannot revert within
   minutes, we cannot deploy confidently.
4. **Infrastructure is code.** No console changes — an unreviewed console change
   is an unauditable one and is invisible to the next person.
5. **Observability precedes traffic.** A deployed service without telemetry is a
   service we cannot operate.

---

## CI/CD Pipeline

### Pull request pipeline — target under 10 minutes (M-4)

Stages run in parallel where possible; only affected targets run (D-104).

| Stage | Blocking | Notes |
| --- | --- | --- |
| Format and lint | Yes | Fastest feedback first |
| Type / static analysis at max strictness | Yes | |
| Architecture and boundary tests | Yes | Enforces `05` and `11` |
| Unit tests | Yes | Domain coverage gate (M-3) |
| Integration tests | Yes | Real Postgres and Redis |
| Feature / API tests | Yes | Includes negative authorization cases |
| **Tenant isolation suite** | Yes | T-1, T-7 |
| Contract verification | Yes | API contract conformance |
| Secret scanning | Yes | SEC-3 |
| SAST / SCA / IaC scanning | Yes | Blocking on critical/high |
| Container build and scan | Yes | |
| Bundle size budget | Yes | P-4 |
| Preview environment deploy | No | Ephemeral, per PR |
| Adversarial AI suite | Yes | SEC-9 |

**Ordering is deliberate:** cheapest and fastest checks run first so that a
formatting error fails in 30 seconds rather than after eight minutes of tests.
Feedback latency shapes developer behaviour more than any policy does.

### Post-merge pipeline

Build once → deploy the same artifact everywhere. **Rebuilding per environment
means production runs a binary that was never tested**, which silently defeats
the entire pipeline.

```
main → build immutable artifact (content-addressed tag)
     → deploy to staging automatically
     → run E2E + smoke tests against staging
     → [manual promotion gate]
     → deploy to production (progressive)
     → verify health and SLOs
     → auto-rollback on breach
```

**Scheduled separately:** full AI evaluation suite (nightly), load tests (per
phase milestone), dependency scans (daily), soak tests (quarterly).

### Manual promotion — and why it stays for now

Staging → production requires human approval initially. Once deploy confidence
is established — measured by change failure rate, not by feeling — the gate is
removed in favour of full continuous deployment. Stating the *exit criterion*
now prevents the gate becoming permanent by inertia, which is its usual fate.

---

## Deployment Mechanics

### Rolling deployment with health gating

New instances start, pass health checks, receive traffic; old instances drain
and terminate. No downtime, no double capacity cost.

**Requirements this imposes** — both already established, which is why rolling
deployment is viable:

- **Backward-compatible schema changes** (D-120). Two application versions run
  simultaneously against one schema. This is the single most common cause of
  deploy-time incidents.
- **Backward-compatible API changes** during the rollout window, since clients
  may hold either version.

**Health checks are two distinct probes**, and conflating them causes outages:

- **Liveness** — is the process alive? Failure means restart.
- **Readiness** — can it serve traffic (dependencies reachable, migrations
  applied, caches warm)? Failure means remove from the load balancer.

A readiness check that merely returns 200 without verifying dependencies routes
traffic to an instance that cannot serve it, which is worse than no check at all
because it looks healthy.

### Progressive delivery

For higher-risk changes, deploy to a small traffic percentage first, monitor
error rates and latency, then proceed or abort automatically. Combined with
feature flags (D-117), this decouples deploy from release: code ships dark, then
is enabled for a cohort, then everyone. **Deployment and release become separate
decisions with separate risk profiles** — which is what allows frequent deploys
without frequent user-visible risk.

### Rollback

| Change type | Rollback |
| --- | --- |
| Application code | Redeploy previous immutable artifact — minutes |
| Feature behaviour | Flag off — seconds, no deploy |
| Configuration | Revert config, redeploy |
| Database schema | **Forward-only** — a new migration (D-120) |

**Database rollback is deliberately not offered.** A reverse migration against
data written by the new version usually loses data. The mitigation is
expand-contract: because each step is backward compatible, reverting the
*application* is always safe, and the schema simply stays ahead. This is the
whole reason expand-contract is mandatory rather than recommended.

### Zero-downtime worker deploys

Workers must finish or safely abandon in-flight jobs: graceful shutdown with a
drain period, jobs made idempotent (D-37) so redelivery is harmless, long jobs
checkpointed. A worker killed mid-job without these produces duplicate artifacts
and duplicate AI spend.

---

## Infrastructure Management

### Infrastructure as code

All infrastructure in Terraform (D-49), reviewed like application code, with
remote state and locking. Environments share modules with per-environment
variables, so staging genuinely resembles production — a staging environment
built differently tests a different system.

### Environment topology

| Environment | Sizing | Isolation |
| --- | --- | --- |
| Preview | Minimal, shared cluster | Namespace per PR |
| Staging | Production-shaped, smaller | Separate account/project |
| Production | Full HA, multi-AZ | Separate account/project |

**Separate cloud accounts** for staging and production, not merely separate
namespaces. Account boundaries are the strongest available blast-radius control:
a misconfiguration or compromised credential in staging cannot touch production.

### Production topology

- Multi-AZ across at least three availability zones.
- API and workers auto-scaled behind a load balancer, minimum two instances per
  service (a single instance is a guaranteed outage during deploy).
- Postgres primary with synchronous standby in another AZ, plus read replicas.
- Redis in HA configuration with automatic failover.
- Object storage with cross-region replication for durability.
- CDN and WAF at the edge.

Single-region initially. Multi-region is Phase 4, driven by data residency
(C-4) rather than availability — the complexity of multi-region active-active is
not justified by our availability targets alone, and pretending otherwise is a
common and expensive mistake.

### Secrets

Managed secret store, injected at runtime, never in images or repositories.
Rotation is scheduled and automated. Access is workload identity, not
credentials — long-lived credentials are the ones that leak.

---

## Observability in Operation

Instrumentation is defined in `04` (O-1 to O-6); this is its operational use.

### SLOs and error budgets

| SLO | Target | Alert |
| --- | --- | --- |
| API availability | 99.9% (A-1) | Burn rate |
| API latency | p95 within P-1/P-2 | Burn rate |
| AI job success | 99.5% (A-3) | Burn rate |
| Queue age | < 5 min user-facing | Threshold |

**Alerting on error budget burn rate rather than raw thresholds** (O-4) is the
difference between actionable alerts and alert fatigue. A single 500 is noise;
consuming 10% of the monthly budget in an hour is an incident. Teams that alert
on raw thresholds stop reading alerts within a month.

**Every alert is actionable and has a runbook.** An alert with no defined
response is noise, and noise is what makes real alerts invisible.

### Dashboards

Per-service health, per-tenant usage and cost (O-5), queue depth and age, AI
quality trends, database health. **Tenant-attributed telemetry throughout**
(O-3) — "the API is slow" is nearly useless; "tenant 4471's traversals are slow"
is immediately actionable.

---

## Incident Readiness

- On-call rotation with defined severity levels and escalation.
- Runbooks for every alert, kept current — an out-of-date runbook is worse than
  none, because it is trusted.
- Kill switches for every AI workflow and external integration (D-119).
- Status page for customer communication.
- Blameless post-incident review with tracked, scheduled actions.
- Regular incident drills, because a process first exercised during a real
  incident is a process that fails during a real incident.

**Error budget policy** (D-28): exhausting the monthly budget freezes feature
deployment in favour of reliability work. Agreed in advance so it is a rule
rather than a negotiation while everyone is stressed.

---

## Disaster Recovery

| Scenario | Response | Target |
| --- | --- | --- |
| Instance failure | Auto-replacement | Automatic |
| AZ failure | Multi-AZ failover | Automatic |
| Database failure | Standby promotion | < 5 min |
| Data corruption | Point-in-time recovery | RTO 4h / RPO 15 min (A-6, A-7) |
| Region failure | Rebuild from IaC + backups | Best effort initially; improves at Phase 4 |
| Accidental deletion | PITR + object versioning | < 1 h |

**Quarterly restore drills, on a schedule, with results recorded.** An untested
backup is not a backup — it is an assumption. Most backup failures are
discovered during the first real restore, which is the worst possible moment to
discover them.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-149 | Build once, deploy the identical artifact everywhere | Rebuilding per environment ships an untested binary to production |
| D-150 | Cheapest pipeline stages first | Feedback latency shapes behaviour more than policy |
| D-151 | Rolling deployment with separate liveness and readiness probes | Conflating them routes traffic to instances that cannot serve |
| D-152 | Progressive delivery for higher-risk changes | Separates deployment risk from release risk |
| D-153 | Database changes are forward-only; rollback is application-only | Reverse migrations against new data lose data |
| D-154 | Graceful worker drain with idempotent, checkpointed jobs | Prevents duplicate artifacts and duplicate AI spend |
| D-155 | Separate cloud accounts for staging and production | Account boundaries are the strongest blast-radius control |
| D-156 | Minimum two instances per service | A single instance guarantees downtime on every deploy |
| D-157 | Single region initially; multi-region deferred to Phase 4 for residency | Multi-region complexity is not justified by our availability targets |
| D-158 | Alert on error budget burn rate, not raw thresholds | Raw-threshold alerting produces fatigue and ignored alerts |
| D-159 | Every alert has a runbook; alerts without one are deleted | Unactionable alerts hide actionable ones |
| D-160 | Manual production gate with a defined exit criterion | Prevents the gate becoming permanent by inertia |
| D-161 | Quarterly restore drills with recorded results | An untested backup is an assumption |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Pipeline exceeds 10 minutes | Batching; trunk-based development collapses | Affected-target detection; parallelization; duration tracked as a metric |
| Backward-incompatible migration reaches production | Rollout-window outage | Expand-contract mandatory; automated detection of destructive changes |
| Manual promotion gate becomes permanent | Deploy frequency drops; batch size grows | Exit criterion defined now and reviewed |
| Preview environments accumulate cost | Unexpected spend | Auto-destroy on PR close; TTL on idle |
| Alert fatigue causes real incidents to be missed | Prolonged outages | Burn-rate alerting; runbook requirement; regular alert review |
| Restore procedure fails when actually needed | Data loss (A-6/A-7 breached) | Quarterly drills with recorded outcomes |
| Terraform state corruption or drift | Infrastructure changes blocked mid-incident | Remote state with locking and versioning; drift detection in CI |
| Single-region outage exceeds recovery expectations | Extended downtime | Documented and communicated honestly; revisited at Phase 4 |

## Dependencies

- **Depends on:** NFRs (`04`), technology (`06`), development strategy (`12`),
  testing strategy (`14`).
- **Depended on by:** quality gates, definition of done, roadmap.

## Future Improvements

- Remove the manual production gate once change failure rate justifies it.
- Add automated canary analysis so progressive delivery decisions are
  metric-driven rather than human-judged.
- Add automated destructive-migration detection in the PR pipeline.
- Design multi-region topology ahead of Phase 4 residency requirements.
