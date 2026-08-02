# Non-Functional Requirements

## Purpose

State the measurable quality attributes the platform must meet. Non-functional
requirements are the real architectural drivers — functional requirements can
usually be satisfied by many designs, but the availability, latency, isolation
and cost targets below eliminate most of them. Every NFR here is falsifiable and
has a verification method, because an unmeasurable NFR is an aspiration.

## Scope

**In scope:** availability, performance, scalability, isolation, security,
compliance, data protection, observability, cost, accessibility, and
maintainability targets, each with a verification method and target phase.

**Out of scope:** how each target is achieved. Mechanisms live in the
scalability, security, and deployment strategies.

---

## Reading the Targets

- **Phase** — when the target becomes binding (`docs/governance/20-roadmap.md`).
  Targets are not all due at once; committing to 99.99% availability in Phase 1
  would be dishonest and would misdirect engineering effort.
- **Verification** — how we prove it. An NFR without a verification method is
  not a requirement.
- Targets are **directional engineering commitments**, not contractual SLAs.
  Customer-facing SLAs are set commercially, below these numbers, with margin.

---

## Availability and Reliability

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| A-1 | Core API availability | 99.9% monthly (≈43 min downtime) | 1 | Synthetic probes + uptime monitoring |
| A-2 | Core API availability at scale | 99.95% monthly | 4 | As above |
| A-3 | AI generation availability | 99.5% monthly | 1 | Job success rate excluding user-caused failures |
| A-4 | No single points of failure in the request path | Zero SPOF | 2 | Architecture review + failure injection |
| A-5 | Graceful degradation when AI providers fail | Core CRUD unaffected | 1 | Chaos test with provider unreachable |
| A-6 | Recovery Point Objective | 15 min (Ph1) → 5 min (Ph4) | 1 | Restore drill, quarterly |
| A-7 | Recovery Time Objective | 4 h (Ph1) → 1 h (Ph4) | 1 | Restore drill, quarterly |

**A-5 is load-bearing.** AI providers are third parties with their own
incidents and rate limits. A design where a provider outage takes down login,
navigation, or document reading is unacceptable. This forces AI work onto
asynchronous paths — which in turn shapes the entire request architecture.

**A-6/A-7 note:** an untested backup is not a backup. Restore drills are
scheduled, not aspirational; the drill is the requirement, not the backup
configuration.

**Error budget policy:** exhausting the monthly error budget freezes feature
deployment in favour of reliability work until the budget recovers. Stated here
so it is a rule agreed in advance rather than a negotiation during an incident.

---

## Performance

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| P-1 | Read API latency | p95 < 300 ms, p99 < 800 ms | 1 | APM percentiles, production |
| P-2 | Write API latency | p95 < 500 ms, p99 < 1.2 s | 1 | APM percentiles |
| P-3 | Graph traversal (impact analysis, depth ≤ 5) | p95 < 1 s | 2 | Benchmark on representative dataset |
| P-4 | Web app first contentful paint | < 1.5 s on 4G | 2 | Lighthouse CI budget |
| P-5 | Web app interaction to next paint | < 200 ms | 2 | Real user monitoring |
| P-6 | AI streaming first token | < 3 s p95 | 1 | Instrumented in orchestration service |
| P-7 | Full BRD generation, medium project | < 5 min p95 | 1 | Job duration metrics |
| P-8 | Search results | p95 < 500 ms | 2 | Benchmark |
| P-9 | No synchronous HTTP request performs AI inference | Zero | 1 | Static analysis + architecture test |

**P-9 is an architectural constraint, not a performance tuning goal.** AI
inference takes seconds to minutes with unbounded tail latency. Any design that
blocks an HTTP worker on it will exhaust the worker pool under trivial load and
couple our availability to a third party's. This single rule dictates the
job-and-event architecture, the need for streaming or polling result delivery,
and much of the frontend's state design. It is enforced mechanically because it
will be violated accidentally otherwise.

**On latency targets excluding AI:** P-1 and P-2 apply to conventional
operations. AI-assisted operations are governed by P-6 and P-7 and are always
asynchronous. Conflating the two would make every target meaningless.

---

## Scalability

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| S-1 | Tenants | 1k (Ph2) → 50k (Ph4) | 2 | Load test with representative distribution |
| S-2 | Total users | 10k (Ph2) → 2M (Ph4) | 2 | Load test |
| S-3 | Concurrent active users | 500 (Ph2) → 50k (Ph4) | 2 | Load test |
| S-4 | Sustained API throughput | 100 rps (Ph2) → 5k rps (Ph4) | 2 | Load test |
| S-5 | Peak burst, 5 min | 10× sustained | 2 | Spike test |
| S-6 | Artifacts per tenant | 1M without degradation | 3 | Volume test |
| S-7 | Graph edges per tenant | 10M without degradation | 3 | Volume test |
| S-8 | Stateless horizontal scaling of API and workers | Linear to 50 instances | 1 | Load test at increasing replica counts |
| S-9 | Largest tenant ≤ 5% of total load | Enforced by quota | 3 | Per-tenant usage metrics |

**S-9 exists because multi-tenant load is never uniform.** A single large tenant
running bulk generation can starve everyone else — the noisy-neighbour problem.
Per-tenant rate limits and queue fairness are requirements, not optimizations.

**Deliberately modest early targets.** Designing Phase 1 for 2M users would be
speculative generality and would slow delivery for a load that does not exist.
What Phase 1 *must* do is avoid decisions that foreclose the Phase 4 targets —
statelessness, tenant-scoped data access, and asynchronous processing. This is
the distinction between designing *for* scale and *not designing against* it.

---

## Multi-Tenancy and Isolation

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| T-1 | Zero cross-tenant data access | Absolute | 1 | Automated isolation test suite, blocking CI gate |
| T-2 | Tenant context resolved and enforced at data layer | 100% of queries | 1 | DB-level policy + architecture test |
| T-3 | Per-tenant resource quotas (API, AI spend, storage) | Enforced | 2 | Quota tests |
| T-4 | Per-tenant data export | Complete, machine-readable | 2 | Export integrity test |
| T-5 | Per-tenant deletion within 30 days of request | Verified complete | 2 | Deletion audit |
| T-6 | Enterprise tenants promotable to dedicated schema/DB | No app rewrite | 4 | Migration rehearsal |
| T-7 | AI retrieval scoped to requesting tenant | Absolute | 1 | Retrieval isolation test |

**T-1 and T-7 are the two requirements that can end the company.** A
cross-tenant leak at an agency means one client's confidential project data
reaching a competitor. T-7 is the newer and less obvious failure mode: a
retrieval layer that indexes all tenants and filters after ranking will
eventually surface another tenant's content in generated output. Filtering must
happen *before* retrieval, not after.

Both are verified by an automated suite that blocks merge — the only NFRs given
that status, because they are the only ones where a single failure is
unrecoverable.

---

## Security

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| SEC-1 | Encryption in transit | TLS 1.3, HSTS | 1 | Config scan |
| SEC-2 | Encryption at rest | AES-256, managed keys | 1 | Infrastructure audit |
| SEC-3 | Secrets never in source or images | Zero | 1 | Secret scanning in CI, blocking |
| SEC-4 | Authorization default-deny | 100% of endpoints | 1 | Architecture test |
| SEC-5 | Audit log for security-relevant events | Append-only, ≥ 1 yr | 1 | Audit completeness test |
| SEC-6 | MFA available; step-up for sensitive ops | Available Ph1, enforced Ph3 | 1 | Functional test |
| SEC-7 | Critical/high dependency vulnerabilities | Zero in production | 1 | SCA in CI, blocking |
| SEC-8 | Time to patch critical CVE | < 72 h | 1 | Tracked from disclosure |
| SEC-9 | Prompt injection defences on untrusted content | Enforced | 1 | Adversarial test suite |
| SEC-10 | Independent penetration test | Annual, findings remediated | 3 | Third-party report |
| SEC-11 | No ambient staff access to tenant content | JIT, time-bound, audited | 1 | Access audit |

**SEC-9 is specific to this product's threat model.** The platform ingests
client-supplied documents, emails and briefs, then feeds them to models that
call tools. That is a direct prompt-injection path: hostile text in an uploaded
requirements document attempting to redirect an agent. Treating this as a named
threat class in Phase 1 — rather than discovering it in Phase 3 — is deliberate.

---

## Data Protection and Compliance

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| C-1 | GDPR compliance | Full | 1 | Legal review + DPIA |
| C-2 | Data subject access/erasure | Within 30 days | 2 | Process test |
| C-3 | Tenant data excluded from model training | Contractual + technical | 1 | Provider agreements + config audit |
| C-4 | Data residency selectable (EU/US) | Per tenant | 4 | Regional deployment test |
| C-5 | SOC 2 Type II | Achieved | 4 | External audit |
| C-6 | Backup retention | 35 days point-in-time | 1 | Restore drill |
| C-7 | Records of processing and sub-processors | Current | 1 | Documentation review |

**C-3 is a sales blocker, not a nice-to-have.** The first agency to ask "does
our client's confidential brief train your model?" needs a clear, verifiable
"no", backed by provider agreements and configuration. Discovering this
requirement during a deal is discovering it too late.

---

## Observability

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| O-1 | Distributed tracing across API, workers, AI service | 100% of requests | 1 | Trace completeness check |
| O-2 | Structured logs with correlation and tenant ID | 100% | 1 | Log schema validation |
| O-3 | Every log/metric/trace attributable to a tenant | 100% | 1 | Schema validation |
| O-4 | Alert on SLO burn rate, not raw thresholds | All core SLOs | 2 | Alert review |
| O-5 | Per-tenant AI cost visibility | Real time | 1 | Metering reconciliation vs provider invoice |
| O-6 | Mean time to detect critical incident | < 5 min | 2 | Incident review |

**O-3 is what makes the rest operable.** In a multi-tenant system, "the API is
slow" is nearly useless while "tenant 4471's graph traversals are slow" is
immediately actionable. Retrofitting tenant attribution across telemetry is
tedious and always incomplete, so it is required from the first line.

**O-5 pairs with G5.** AI cost must be observable per tenant in real time or
margin erosion is discovered at the end of the month, after it has happened.

---

## Cost Efficiency

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| $-1 | Gross margin after inference and infrastructure | > 70% | 3 | Unit economics reporting |
| $-2 | AI cost per generated artifact | Tracked, trending down | 1 | Cost per artifact type |
| $-3 | Infrastructure cost per active tenant | Tracked, sub-linear growth | 2 | Cost allocation reporting |
| $-4 | Per-tenant AI spend cap | Enforced, configurable | 2 | Quota test |

Cost is an NFR because in an AI-native product it is an *architectural*
property. Model choice, caching, context size and retry policy determine unit
economics more than infrastructure sizing does. Treating it as a finance
concern discovered quarterly is how AI products end up with negative gross
margin on their most enthusiastic customers.

---

## Usability and Accessibility

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| U-1 | WCAG 2.2 Level AA | Full conformance | 2 | Automated axe + manual audit |
| U-2 | Keyboard navigation for all functions | 100% | 2 | Manual audit |
| U-3 | Responsive from 1280 px; read/approve flows from 375 px | Met | 2 | Cross-device test |
| U-4 | Every AI-generated artifact shows provenance and confidence | 100% | 1 | Functional test |
| U-5 | Every AI-generated artifact is editable before approval | 100% | 1 | Functional test |

**U-4 and U-5 are trust requirements disguised as UX.** An AI artifact a user
cannot inspect the provenance of, or cannot correct, is one they will not sign
their name to — and G4 fails. Full accessibility conformance is targeted at
Phase 2 because Phase 1 has no external users; U-1 is nonetheless designed for
from the first component, since retrofitting accessibility is far more
expensive than building it in.

---

## Maintainability

| ID | Requirement | Target | Phase | Verification |
| --- | --- | --- | --- | --- |
| M-1 | Static analysis at maximum strictness | Zero errors | 1 | CI gate |
| M-2 | Module boundary violations | Zero | 1 | Dependency analysis gate |
| M-3 | Domain-layer test coverage | > 85% meaningful | 1 | Coverage gate on domain paths |
| M-4 | CI feedback on a pull request | < 10 min | 1 | Pipeline duration metric |
| M-5 | New engineer to first merged PR | < 3 days | 2 | Onboarding tracking |
| M-6 | Public API contracts versioned; no breaking change without deprecation | Enforced | 2 | Contract test |

**M-3 avoids a blanket coverage number.** A global "90% coverage" target is
gamed within a sprint by testing getters while leaving domain logic untested.
Requiring high coverage specifically on the domain layer — where the business
rules live — targets the tests that actually prevent defects.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-21 | No AI inference in a synchronous HTTP request (P-9) | Unbounded tail latency would exhaust workers and couple availability to third parties |
| D-22 | Tenant isolation tests are a blocking CI gate (T-1, T-7) | Only failure class that is unrecoverable |
| D-23 | AI retrieval filters by tenant before ranking, never after | Post-filtering leaks under ranking pressure |
| D-24 | Tenant ID required on every log, metric and trace (O-3) | Makes multi-tenant operation diagnosable; cannot be retrofitted |
| D-25 | Cost per artifact is a tracked NFR from Phase 1 ($-2) | In an AI product, unit economics is an architectural property |
| D-26 | Targets are phased, not all binding at launch | Committing to Phase 4 numbers in Phase 1 misdirects effort and is dishonest |
| D-27 | Coverage targeted at the domain layer, not globally (M-3) | Blanket coverage targets get gamed |
| D-28 | Error budget exhaustion freezes feature deploys | Agreeing the rule in advance beats negotiating it mid-incident |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Targets set without production data prove wrong | Over- or under-engineering | Revisit after Phase 1 telemetry; treat as hypotheses |
| P-9 violated under delivery pressure | Worker exhaustion; availability coupled to AI provider | Mechanical enforcement, not review discipline |
| AI cost targets missed as usage grows | Margin collapse | Real-time per-tenant metering from Phase 1; caps in Phase 2 |
| Isolation test suite gives false confidence | Undetected leak path | Suite extended with every new data access pattern; annual pentest as independent check |
| Phase 4 scale targets never materialize | Wasted design effort | Phase 1 avoids foreclosing scale rather than building for it |

## Dependencies

- **Depends on:** business goals (`01`), personas (`02`), module boundaries (`03`).
- **Depended on by:** high-level architecture, technology decisions,
  multi-tenancy, scalability, security, testing, deployment.

## Future Improvements

- Convert targets to formal SLOs with error budgets once production baselines
  exist.
- Add per-endpoint latency budgets rather than a single global target.
- Publish a customer-facing SLA derived from — and comfortably below — A-1.
