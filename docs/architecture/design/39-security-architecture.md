# Security Architecture

## Purpose

Express the security strategy (`09`) as architecture: where trust boundaries
fall, how identity and tenant context propagate, how each container is defended,
and where the untrusted-content path runs. `09` states *what* must be true; this
document states *where in the system* it is made true.

## Scope

**In scope:** trust zones and network segmentation, identity and token flow,
tenant context as an architectural control, service-to-service authentication,
secrets flow, the untrusted content path, and per-container attack surface.

**Out of scope:** security policy, compliance and process (`09`), tenant
isolation mechanics (`07`).

---

## Defence in Depth, Mapped to the Architecture

The security posture is "no control is trusted alone" (D-75). Expressed
architecturally, that means each layer independently prevents the same class of
failure.

| Layer | Control | Prevents alone |
| --- | --- | --- |
| Edge | WAF, DDoS, bot mitigation, coarse rate limits | Volumetric and commodity attacks |
| Network | Private subnets, security groups, egress allowlist | Direct access to data tier; exfiltration |
| Transport | TLS 1.3 everywhere including internal | Interception |
| Identity | Token validation, short TTL, rotation, revocation | Credential replay |
| Middleware | Tenant from claims only, fixed pipeline order | Tenant spoofing |
| Authorization | Default deny, resource-scoped policies | Privilege escalation, IDOR |
| Repository | Tenant-scoped by construction | Accidental unscoped access |
| **Database** | **RLS policies** | **Everything above having failed** |
| Application | Redacting logger, output encoding, parameterized queries | Injection, leakage |
| Verification | Isolation suite blocking merge | Regression |

**The database row is the one that matters most.** Every layer above it depends
on code being correct. RLS depends on configuration being correct, once. That
asymmetry is why it is the backstop.

---

## Network Segmentation

```
   INTERNET
      │  443 only
   ┌──▼──────────────────────────────────────────────────────┐
   │ ZONE 1 · EDGE          public subnet                     │
   │ CDN · WAF · Load Balancer                                │
   └──┬───────────────────────────────────────────────────────┘
      │  LB → app ports only, security-group restricted
   ┌──▼──────────────────────────────────────────────────────┐
   │ ZONE 2 · APPLICATION   private subnets, no public IP     │
   │ Core API · Realtime · Workers · AI Service · Scheduler   │
   │                                                          │
   │  egress ──▶ NAT + allowlist ──▶ Zone E (external SaaS)  │
   └──┬───────────────────────────────────────────────────────┘
      │  workload identity, TLS, no public route
   ┌──▼──────────────────────────────────────────────────────┐
   │ ZONE 3 · DATA          isolated subnets                  │
   │ PostgreSQL · Redis ×3 · Object storage · Secret store    │
   │ NO egress. NO ingress except from Zone 2.                │
   └──────────────────────────────────────────────────────────┘
```

**Decision.** Four zones with default-deny between them, and **egress control as
strict as ingress**.

**Reasoning.** Ingress control is universal; egress control is frequently
omitted, and it is the control that matters after a compromise. An attacker with
code execution in Zone 2 cannot exfiltrate to an arbitrary host if egress is
allowlisted to known providers. Without it, application compromise becomes data
loss immediately.

Zone 3 having **no egress at all** is deliberate: a database has no legitimate
reason to initiate an outbound connection, and one attempting to is either
compromised or misconfigured. Denying it removes an entire exfiltration path.

**Alternatives.** *Flat network with application-level auth* — simpler, and a
single application vulnerability reaches everything. *Service mesh with mTLS
everywhere* — stronger and appropriate at a service count we do not have;
operational cost unjustified for five containers (`23` complexity budget).

**Trade-offs.** Allowlist maintenance, and a denial presents as a confusing
failure until the allowlist is updated — mitigated by logging and alerting on
egress denials rather than failing silently.

**Long-term impact.** Segmentation is far cheaper to establish before there is
traffic than to retrofit onto a running system with undocumented flows.

---

## Identity and Token Flow

```
   ┌────────┐  credentials / SSO assertion   ┌─────────────────┐
   │ Client │───────────────────────────────▶│  Identity       │
   │        │◀───── access (short) + ────────│  (module C1)    │
   └───┬────┘       refresh (rotating)       └─────────────────┘
       │
       │ Bearer access token
   ┌───▼─────────────────────────────────────────────────┐
   │ Core API middleware                                  │
   │  validate signature · check revocation · extract     │
   │  claims: actorId, tenantId, roles, sessionId         │
   │                    │                                 │
   │                    ▼                                 │
   │  TenantContext (immutable, request-scoped)           │
   │        │                    │                        │
   │        ▼                    ▼                        │
   │  DB session var       Job payload / event envelope   │
   │  (RLS enforcement)    (async propagation)            │
   └──────────────────────────────────────────────────────┘
```

**Token design:**

| Property | Value | Reason |
| --- | --- | --- |
| Access token TTL | 15 minutes | Bounds the window a stolen token is useful |
| Refresh token | Rotating, single-use, reuse-detected | Reuse indicates theft — detection invalidates the whole family |
| Revocation | Checked against a deny-list on every request | A password reset or membership revocation must be immediate |
| Claims | Actor, tenant, roles, session | **Never client-modifiable** — tenant comes from here only (D-56) |
| Step-up | Required for role change, billing, contract approval, export, integration credentials | Sensitive actions need fresh proof of identity |

**Refresh token reuse detection is the important mechanism.** Rotation alone
means a stolen refresh token works until it is used by the legitimate client.
Detecting reuse — the same token presented twice — proves one of the two holders
is an attacker, and invalidating the entire token family logs both out. The
legitimate user re-authenticates; the attacker loses access.

**Revocation checked per request** rather than relying on token expiry. A
15-minute window of retained access after revocation is unacceptable for
membership removal (`35`'s `MembershipRevoked`).

---

## Tenant Context as an Architectural Control

Tenant context is a **security control with an architectural lifecycle**, not
merely a parameter. Its integrity properties:

| Property | Mechanism |
| --- | --- |
| **Derived only from validated token claims** | Never from headers, query parameters or body (D-56) |
| **Immutable for the request's lifetime** | Cannot be reassigned mid-request |
| **Bound before any handler executes** | Fixed middleware order (D-318) |
| **Propagated explicitly across every async boundary** | Job payload, event envelope, AI service call |
| **Fatal when absent** | Fail closed, never default (D-57) |
| **Bound to the DB session before any query** | RLS enforcement point |
| **Reset on connection release** | Prevents pool-based leakage across requests |

**The connection-release reset is the subtle one.** A pooled connection retaining
the previous request's tenant session variable serves the next request the wrong
tenant's data — a cross-tenant leak with no code defect visible anywhere. It is
tested explicitly under concurrency (`14`).

---

## Service-to-Service Authentication

| Path | Mechanism |
| --- | --- |
| Core API → AI service | Signed service token, short TTL, mutual TLS, network-restricted |
| Worker → AI service | Same |
| Any service → PostgreSQL | Workload identity, credentials from the secret store, **role without `BYPASSRLS`** (D-55) |
| Any service → object storage | Workload identity, scoped to required prefixes |
| Realtime → Core API | Signed service token |
| Inbound webhooks | Signature verification, timestamp bound, replay protection |

**Workload identity rather than static credentials**, everywhere it is available.
Long-lived credentials are the ones that end up in a repository, an environment
dump or a log. A short-lived identity issued to a running workload cannot be
copied usefully.

**The AI service's database role is separately restricted to read-only** (D-314),
so even full compromise of that service cannot write to the system of record.

---

## The Untrusted Content Path

The platform's distinctive threat surface (`09` SEC-9). Traced architecturally:

```
   Client uploads a brief          ← UNTRUSTED from here
        │
        ▼
   Core API: content-type verification, size limit, malware scan
        │  labelled untrusted in metadata — the label travels with the content
        ▼
   Object storage (isolated prefix, no execution context)
        │
        ▼
   Worker: retrieves for a generation job
        │
        ▼
   AI service: Guardrail Pipeline
        ├── structural isolation — delimited, labelled position,
        │   never concatenated into instruction text
        ├── injection heuristics
        └── PII redaction where applicable
        │
        ▼
   Provider Router → model
        │  Tool access minimized for workflows handling untrusted content
        │  Retrieval remains tenant-scoped REGARDLESS of model output (T-7)
        ▼
   Output Validator: schema conformance; non-conformant output rejected
        │
        ▼
   Draft artifact ── requires HUMAN APPROVAL before it is authoritative (D-36)
```

**The architectural insight: scope is enforced outside the model.** Retrieval
tenant-scoping, tool availability and the approval gate are all decided by code
the model cannot influence. A fully successful prompt injection therefore
produces a *draft that a human reviews* — not an action, not a cross-tenant read,
not a state change.

This is containment rather than prevention, stated honestly in `09`: injection is
not fully solvable with current model technology, so the architecture bounds the
blast radius instead of relying on the model resisting attack.

**The label travels with the content.** Content untrusted at upload remains
untrusted in object storage, in the worker, and in the AI service — trust is not
conferred by having crossed a boundary (D-303).

---

## Per-Container Attack Surface

| Container | Exposure | Principal threats | Controls |
| --- | --- | --- | --- |
| **Edge** | Public | DDoS, bots, commodity exploits | WAF, rate limits, managed mitigation |
| **Core API** | Via LB only | Injection, IDOR, authz bypass, tenant spoofing | Default deny, RLS, parameterized queries, fixed middleware order |
| **Realtime** | Public WSS | Unauthorized subscription, resource exhaustion | Auth at connect and re-validated; publish-time authz (D-328); bounded buffers |
| **Workers** | No inbound | Poisoned job payloads | Payload validation; tenant context fail-closed; idempotency |
| **AI service** | Internal only | Prompt injection, cross-tenant retrieval, cost abuse | Guardrails, pre-ranking tenant filter, read-only DB, loop and budget limits |
| **Scheduler** | No inbound | Duplicate execution | Leader election plus idempotency |
| **PostgreSQL** | Zone 3 only | Unauthorized access, isolation bypass | Network isolation, workload identity, RLS, no `BYPASSRLS` |
| **Redis** | Zone 3 only | Unauthorized access, cross-tenant key access | Network isolation, auth, tenant-structural keys |
| **Object storage** | Signed URLs only | Key enumeration, long-lived URL leakage | Tenant-prefixed keys, short expiry, no public access |

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-424 | Four network zones with default-deny between them | A flat network turns one application vulnerability into total reach |
| D-425 | Egress allowlisted as strictly as ingress | Egress control is what limits damage after compromise |
| D-426 | Zone 3 has no egress at all | A datastore initiating outbound traffic is compromised or misconfigured |
| D-427 | Access tokens 15 minutes; refresh rotating with reuse detection | Reuse proves theft; family invalidation ejects the attacker |
| D-428 | Revocation checked per request, not left to token expiry | Membership revocation must be immediate |
| D-429 | Step-up authentication for sensitive operations | Fresh proof of identity where consequences are high |
| D-430 | Tenant context is immutable, fail-closed, and reset on connection release | Pool retention of session state is a leak with no visible code defect |
| D-431 | Workload identity over static credentials wherever available | Long-lived credentials are the ones that leak |
| D-432 | AI service holds a read-only database role | Full compromise of it cannot corrupt the system of record |
| D-433 | Untrusted labelling travels with content across every boundary | Trust is not conferred by crossing a boundary |
| D-434 | Injection is contained architecturally: scope enforced outside the model | Prevention is not achievable; bounding the blast radius is |
| D-435 | Service-to-service calls use signed short-lived tokens plus network restriction | Two independent controls, neither trusted alone |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Egress allowlist blocks a legitimate new integration | Broken feature, confusing failure | Denials logged and alerted; allowlist is reviewed IaC |
| Connection pool leaks tenant session state | Cross-tenant exposure with no visible defect | Set on checkout, cleared on release, tested under concurrency |
| Refresh reuse detection produces false positives | Users logged out spuriously | Tolerance for legitimate races; monitored rate |
| Prompt injection succeeds despite guardrails | Bounded — a draft, not an action | Human approval gate; tenant scope outside model control; adversarial suite |
| Service token compromise | Lateral movement between containers | Short TTL, network restriction, mTLS — all three required |
| Signed URL leaked and shared | Unauthorized blob access until expiry | Short expiry; tenant-prefixed keys prevent traversal |
| Zone segmentation eroded by a convenience rule | Defence in depth reduced silently | Network rules are IaC, reviewed; drift detection in CI |

## Dependencies

- **Depends on:** security strategy (`09`), tenancy (`07`), system context
  (`29`), containers (`30`), components (`31`).
- **Depended on by:** AI integration (`40`), resilience (`41`).

## Future Improvements

- Add mutual TLS across all internal paths once the container count justifies a
  service mesh.
- Formal threat model per container as each is implemented.
- Automated egress-denial reporting so allowlist gaps surface as signals.
