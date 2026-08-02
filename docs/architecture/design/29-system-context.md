# System Context (C4 Level 1)

## Purpose

Define the platform's boundary: who uses it, what it depends on, what crosses
the perimeter, and which of those dependencies can fail without taking the
product down. The context diagram is the first architectural artifact because
everything inside the boundary is negotiable and everything crossing it is a
contract with someone else.

## Scope

**In scope:** actors, external systems, trust zones, data crossing the
perimeter, and criticality classification of every external dependency.

**Out of scope:** internal structure (`30`, `31`) and security controls in depth
(`09`, `39`).

---

## Context Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                            PEOPLE                                        │
│                                                                          │
│  Tenant Members            External Stakeholders      Platform Operators │
│  (BA, Architect, Dev,      (Client contacts —         (our staff —       │
│   QA, Delivery Mgr,         approve, comment,          no ambient tenant │
│   Tenant Admin, Founder)    read shared artifacts)     data access)      │
└───────────┬────────────────────────┬──────────────────────────┬─────────┘
            │                        │                          │
            │ HTTPS / WSS            │ HTTPS (scoped grants)    │ HTTPS (JIT)
            ▼                        ▼                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                          │
│                  AI-NATIVE ENGINEERING PLATFORM                          │
│                                                                          │
│   Turns an idea into a delivered product, keeping every artifact          │
│   linked in one traceable, tenant-owned Delivery Graph.                   │
│                                                                          │
└──┬────────┬────────┬────────┬────────┬────────┬────────┬────────┬───────┘
   │        │        │        │        │        │        │        │
   ▼        ▼        ▼        ▼        ▼        ▼        ▼        ▼
┌──────┐┌───────┐┌───────┐┌───────┐┌───────┐┌───────┐┌───────┐┌─────────┐
│Model ││Customer││ Git   ││ Issue ││E-sign ││Payment││ Comms ││Observ.  │
│Prov- ││ IdP    ││ Prov- ││Tracker││Provider││Provider││(Slack,││Backend  │
│iders ││(SSO/   ││ iders ││(Jira, ││(Docu- ││(Stripe)││ email)││(managed)│
│      ││ SCIM)  ││(GitHub)││Linear)││Sign)  ││       ││       ││         │
└──────┘└───────┘└───────┘└───────┘└───────┘└───────┘└───────┘└─────────┘
 DEGRAD.  CRITICAL  DEGRAD.  DEGRAD.  DEGRAD.  DEGRAD.  DEGRAD.  DEGRAD.
```

---

## Actors

Derived from the personas in `02`, grouped by how they authenticate and what
they may reach — the two attributes that matter architecturally.

| Actor group | Personas | Identity | Access scope |
| --- | --- | --- | --- |
| **Tenant members** | P1–P7, P9 | Platform account or tenant SSO | Their tenant, scoped by role and project membership |
| **External stakeholders** | P8 | Separate external identity | Only explicitly granted resources, one project, expiring (D-60) |
| **Platform operators** | P10 | Internal identity + step-up | System telemetry; tenant data only via just-in-time, audited grant (D-76) |
| **Automated integrations** | — | Tenant-scoped API credentials | Constrained to the granting tenant, per-integration scopes |

**Three distinct identity domains, deliberately.** Tenant members, external
stakeholders and operators are not the same identity system with different
roles — they are separate populations with different lifecycle, different
authentication paths and different blast radius. Modelling external
stakeholders as low-privilege tenant members is the design error that leads to
capability creep as roles gain permissions (D-60).

---

## External Systems

### Criticality classification

**Decision.** Every external dependency is classified as *critical* (its failure
prevents core use) or *degradable* (its failure removes a capability while the
platform continues), and degradable is the default that must be designed for.

**Reasoning.** An architecture that treats all external dependencies alike ends
up as available as the least reliable one. Availability is multiplicative across
hard dependencies: eight dependencies at 99.9% each yield 99.2% if all are
critical — well below our A-1 target — before any of our own failures. The only
way to hit 99.9% while depending on eight third parties is for almost none of
them to be critical.

**Alternatives.** *Treat all as critical* — simplest to build, and mathematically
incapable of meeting A-1. *Treat all as degradable* — requires fallback paths for
things that genuinely have none (a user cannot log in if their IdP is down),
producing complexity that pretends to solve an unsolvable case.

**Trade-offs.** Degradation paths cost design and testing effort, and each is a
partially-working state that must be tested, communicated in the UI, and
reasoned about during incidents. The degradation matrix in `41` exists because
these states multiply.

**Benefits.** Our availability is decoupled from the aggregate availability of
our suppliers. A model provider outage becomes a reduced-capability day rather
than an outage.

**Long-term impact.** The number of external dependencies only grows. Without
this discipline applied from the first integration, the platform's availability
ceiling silently falls with every integration added.

### The dependencies

| System | Purpose | Criticality | Behaviour on failure |
| --- | --- | --- | --- |
| **Model providers** | AI inference for all generation workflows | **Degradable** | Fail over to alternate provider; else queue and retry. All non-AI function continues (A-5). |
| **Customer identity providers** (SAML/OIDC, SCIM) | Enterprise SSO and provisioning | **Critical** *for affected tenants only* | Those users cannot authenticate. Existing sessions survive their TTL. Break-glass local admin retained per tenant. |
| **Git providers** | Repository, branch and PR linkage | Degradable | Sync pauses and resumes; linkage backfills. Cached state remains readable. |
| **Issue trackers** | Bidirectional task sync | Degradable | Sync queues; platform remains the source of truth for its own tasks. |
| **E-signature** | Contract execution | Degradable | Contracts generate and are stored; signature dispatch queues. |
| **Payment provider** | Subscriptions, invoicing | Degradable | Access unaffected; billing operations queue. Never gate product access on a synchronous payment call. |
| **Communication** (email, Slack/Teams) | Notifications, invitations | Degradable | Notifications queue and retry. In-app notification remains authoritative. |
| **Observability backend** | Telemetry storage and alerting | Degradable | Collector buffers; **we lose visibility, not function**. Alerting gap is itself alerted via a separate path. |
| **Object storage / CDN** | Assets, uploads, exports | **Critical** | Managed, multi-AZ, replicated. Treated as infrastructure rather than a third party. |

**Customer IdP is the only genuinely critical external dependency**, and its
blast radius is one tenant rather than the platform. The mitigation is a
break-glass local administrator account per tenant — deliberately, because
"nobody at the customer can log in until their IdP recovers" is an unacceptable
support position, and the alternative (us bypassing their IdP) is worse.

**The observability entry is worth noting:** losing telemetry does not stop the
product, but it does stop us knowing whether the product is working. The
collector buffers locally, and a dead-man's-switch alert on a separate path
detects the case where alerting itself has failed — otherwise the failure mode
is silence, which is indistinguishable from health.

---

## Trust Zones

```
   ZONE 0 · UNTRUSTED          Public internet, client browsers,
                               user-supplied documents, webhook payloads
   ───────────────────────────────────────────────────────────────
   ZONE 1 · EDGE               CDN, WAF, load balancer
                               TLS termination, DDoS, bot control, rate limits
   ───────────────────────────────────────────────────────────────
   ZONE 2 · APPLICATION        Core API, Realtime Gateway, Workers,
                               AI Orchestration — private subnets, no public IP
   ───────────────────────────────────────────────────────────────
   ZONE 3 · DATA               PostgreSQL, Redis, object storage, secrets
                               Reachable only from Zone 2, never from 0 or 1
   ───────────────────────────────────────────────────────────────
   ZONE E · EXTERNAL           Third-party SaaS — outside our control,
                               reached from Zone 2 via egress control
```

**Rules across boundaries:**

- Zone 0 → 1 only. Nothing in Zone 2 or 3 is publicly routable.
- Zone 1 → 2 through the load balancer only, on defined ports.
- Zone 2 → 3 with workload identity, never static credentials.
- Zone 2 → E through an egress allowlist. **Outbound is controlled, not just
  inbound** — this is what limits exfiltration if application code is
  compromised, and it is the control most often omitted.
- **Content from Zone 0 stays labelled as untrusted through every zone it
  reaches.** An uploaded client document is untrusted in the AI service just as
  much as at the edge (SEC-9). Trust is not conferred by having crossed a
  boundary.

That last rule is the architectural expression of the prompt-injection threat
model: the danger is not that hostile content enters, but that it is treated as
trusted once inside.

---

## Data Crossing the Perimeter

Enumerated because every outbound flow is a disclosure decision and a
sub-processor obligation (C-7).

| Direction | Data | Destination | Control |
| --- | --- | --- | --- |
| Outbound | Prompt context — requirements, discovery notes, retrieved artifacts | Model providers | Contractual no-training (C-3); PII redaction where applicable; tenant-scoped assembly |
| Outbound | Contract documents, signer identities | E-signature provider | DPA; field-level encryption at rest before dispatch |
| Outbound | Billing identifiers, amounts | Payment provider | No card data touches us — the reason PCI is out of scope (D-87) |
| Outbound | Notification content, recipients | Email, Slack/Teams | Minimized payloads; deep links rather than content |
| Outbound | Telemetry — traces, metrics, logs | Observability backend | **Tenant IDs, never tenant content** (D-84) |
| Inbound | Repository metadata, commits, PRs | Git providers | Anticorruption layer; treated as untrusted |
| Inbound | Uploaded documents, briefs | Users | Content-type verification, malware scan, untrusted labelling |
| Inbound | Webhooks | Integrations | Signature verification; replay protection; untrusted labelling |
| Bidirectional | Identity assertions | Customer IdP | Signature validation, clock skew bounds, replay protection |

**The first row is the one that requires the most care.** Prompt context is the
largest volume of customer-confidential data leaving our perimeter, and it goes
to a third party. This is why C-3 is a sales blocker rather than a nicety, why
retrieval is tenant-scoped before ranking (D-23), and why context assembly is
deliberate rather than "send everything relevant."

**The telemetry row is the one most often got wrong.** Logs and traces routinely
leak content — a request body captured for debugging, an error message
containing a document excerpt. The redacting logger (D-79) is what makes this
row true by construction rather than by reviewer vigilance.

---

## What Is Deliberately Not an External Dependency

| Not depended on | Reason |
| --- | --- |
| A third-party agent/AI platform | Would surrender control of retrieval scoping (T-7) and the component closest to the moat (D-238) |
| A hosted vector database (initially) | pgvector keeps tenant scoping a database-level guarantee rather than an application-level one (D-45) |
| A third-party feature-flag service | Flags gate security-relevant behaviour and kill switches; a dependency that can fail open is unacceptable |
| An external authorization service | Authorization must work when the network does not |
| A commercial APM agent as the only instrumentation | OTel keeps the backend replaceable (D-50) |

Stated explicitly because each will be proposed as a convenience, and the answer
is decided once rather than per proposal.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-298 | Every external dependency is classified critical or degradable; degradable is the default | Availability is multiplicative; eight critical dependencies cannot yield 99.9% |
| D-299 | Only customer IdP is critical, and its blast radius is one tenant | Everything else has a designed degradation path |
| D-300 | Break-glass local admin retained per tenant even with SSO enforced | "Nobody can log in until your IdP recovers" is an unacceptable position |
| D-301 | Three separate identity domains: members, external stakeholders, operators | Different lifecycle, authentication and blast radius; modelling stakeholders as members causes capability creep |
| D-302 | Egress from Zone 2 is allowlisted, not just ingress | Limits exfiltration if application code is compromised |
| D-303 | Untrusted content stays labelled untrusted across every zone | Trust is not conferred by crossing a boundary — the core of the injection threat model |
| D-304 | Telemetry carries tenant identifiers, never tenant content | Otherwise the observability backend becomes a second copy of confidential data |
| D-305 | Dead-man's-switch alert on a separate path from primary alerting | Observability failure otherwise presents as silence, indistinguishable from health |
| D-306 | Product access is never gated on a synchronous payment provider call | Makes a billing outage a customer-facing outage |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| A dependency assumed degradable proves critical in practice | Unplanned outage | Degradation paths tested by failure injection, not assumed (`41`) |
| Customer IdP outage locks out an enterprise tenant | Severe customer impact | Break-glass admin; session TTL survives short outages |
| Prompt context leaks more than intended to providers | Confidentiality breach | Tenant-scoped assembly; retrieval precision; redaction; contractual controls |
| Egress allowlist becomes stale and blocks a legitimate integration | Broken feature, slow diagnosis | Allowlist changes are reviewed IaC; denials are logged and alerted |
| Sub-processor list drifts from actual data flows | Compliance violation (C-7) | Perimeter data table reviewed at phase boundaries; new outbound flow requires an ADR |
| New integrations added without criticality classification | Availability ceiling erodes silently | Classification is a required field for any new external dependency |

## Dependencies

- **Depends on:** personas (`02`), NFRs (`04`), security strategy (`09`).
- **Depended on by:** container architecture (`30`), security architecture
  (`39`), resilience (`41`).

## Future Improvements

- Publish the sub-processor register derived from the perimeter data table.
- Add automated egress-denial alerting so allowlist gaps surface as signals
  rather than mysteries.
- Model per-tenant availability separately from platform availability once
  enterprise SSO tenants exist — their availability includes their own IdP.
