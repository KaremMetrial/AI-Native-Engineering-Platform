# Observability Architecture

## Purpose

Define how the platform is instrumented and how that telemetry becomes
diagnosis. `04` states the observability requirements and `15` covers their
operational use; this document specifies the telemetry architecture itself —
signal design, propagation, cardinality, sampling and correlation.

In a multi-tenant, event-driven, partly-asynchronous system, observability is not
a supporting concern. It is the only way to know what happened.

## Scope

**In scope:** the telemetry pipeline, trace design, context propagation across
async boundaries, log design, metric design and cardinality, sampling,
correlation, alerting architecture and cost control.

**Out of scope:** SLO definitions (`04`), incident response (`15`).

---

## Instrumentation Standard

**Decision.** OpenTelemetry for traces, metrics and logs, across all three
runtimes, exported through a collector to a managed backend.

**Reasoning.** A single vendor-neutral instrumentation standard means the
application is instrumented once and the backend is replaceable (D-50). Vendor
agent SDKs produce faster initial results and permanent lock-in on a component
whose cost grows with traffic — a poor trade for a decade-long horizon.

OTel also solves the cross-language problem directly: trace context propagates
between the PHP core, the Python AI service and the TypeScript frontend using one
standard, which is what makes end-to-end tracing possible at all in a polyglot
system.

**Alternatives.** *Vendor agents per language* — best-in-class per runtime, three
different data models, and lock-in. *Custom instrumentation* — total control,
and reinvents context propagation, sampling and semantic conventions badly.
*Logs only* — the historical default; cannot answer "where did the time go" in a
distributed request without heroic effort.

**Trade-offs.** OTel's ecosystem maturity varies by language; the collector is
another component to run and monitor.

**Benefits.** One data model, one propagation standard, one place to enforce
redaction and sampling, and a replaceable backend.

**Long-term impact.** Observability backends are among the most expensive
long-term vendor relationships. Keeping the exit cheap is worth the collector.

---

## Telemetry Pipeline

```
   Core API ─┐
   Workers  ─┤
   AI svc   ─┼──▶ OTel Collector ──┬──▶ Traces backend
   Realtime ─┤    (per node)       ├──▶ Metrics backend
   Frontend ─┘                     └──▶ Log backend
                    │
                    ├── redaction (final enforcement point)
                    ├── sampling (tail-based)
                    ├── cardinality limiting
                    └── buffering + disk spill on backend failure
```

**The collector is the enforcement point**, and that is its main architectural
justification beyond decoupling:

| Function | Why at the collector |
| --- | --- |
| **Redaction** | Last line of defence before data leaves our perimeter (D-304). Application-level redaction (D-79) is primary; this catches what slipped through. |
| **Tail-based sampling** | Requires seeing the complete trace — only possible after collection, not at emission |
| **Cardinality limiting** | One place to enforce it, rather than trusting every emitter |
| **Buffering** | Backend outage degrades to buffered, not lost (D-305) |

---

## Trace Design

### Span structure

A trace covers one logical operation end to end, **including its asynchronous
continuation**.

```
  TRACE: "Generate BRD"  (correlationId = trace id)
  │
  ├─ SPAN  http.POST /projects/{id}/brd:generate        [Core API]     180ms
  │   ├─ SPAN  auth.resolve                                             8ms
  │   ├─ SPAN  authz.check                                              5ms
  │   ├─ SPAN  db.insert job + outbox            (one transaction)     40ms
  │   └─ SPAN  queue.enqueue                                            6ms
  │
  ├─ SPAN  job.brd_generate                            [Worker]      94,000ms
  │   │    ⚡ LINKED to the enqueue span, not nested under the request
  │   ├─ SPAN  context.restore                                          3ms
  │   ├─ SPAN  ai.invoke_workflow                      [AI service]  91,000ms
  │   │   ├─ SPAN  context.graph_traversal                            420ms
  │   │   ├─ SPAN  context.vector_retrieval                           180ms
  │   │   ├─ SPAN  guardrail.input                                     30ms
  │   │   ├─ SPAN  provider.generate  (model, tokens, cost)        89,000ms
  │   │   └─ SPAN  output.validate                                    140ms
  │   ├─ SPAN  db.persist_version                                     210ms
  │   └─ SPAN  outbox.write                                            18ms
  │
  └─ SPAN  realtime.push                               [Realtime]       12ms
```

**Async continuation uses span *links*, not parent-child nesting.** A job
triggered by a request is causally related but temporally independent — the
request completed in 180ms while the job ran for 94 seconds. Nesting the job
under the request would produce a 94-second HTTP span, destroying the latency
metrics derived from it. Links preserve causality without corrupting duration.

**This is the piece most teams get wrong**, and getting it wrong means either
async work is invisible in traces or synchronous latency metrics are nonsense.

### Required span attributes

| Attribute | On every span | Why |
| --- | --- | --- |
| `tenant.id` | Yes (O-3) | "The API is slow" versus "tenant 4471 is slow" |
| `correlation.id` | Yes | Ties the logical operation across all hops |
| `actor.id` | Where an actor exists | Who triggered it |
| `deployment.version` | Yes | Correlate regressions with releases |
| `error.type` | On failure | Aggregate failure classification |
| `ai.model`, `ai.prompt_version`, `ai.tokens`, `ai.cost` | AI spans | Cost and quality attribution (O-5) |
| `db.statement` | **Never** | May contain tenant content |

**`db.statement` is excluded deliberately.** Query text with inlined parameters
is tenant content, and traces are retained and widely readable. Operation name
and table are captured instead — enough to diagnose, insufficient to leak.

---

## Context Propagation

Trace context must survive every boundary or the trace fragments into
unconnectable pieces.

| Boundary | Mechanism |
| --- | --- |
| Client → API | W3C `traceparent` header |
| API → AI service | `traceparent` header |
| **API → queue → worker** | **Embedded in the job payload** |
| **Outbox → event → consumer** | **Carried in the event envelope** (`35`) |
| Worker → external API | `traceparent` where supported |
| Redis pub/sub → Realtime | Carried in the message |

**The queue and event boundaries are where propagation is lost by default**, and
their loss is the most damaging: the asynchronous half of the system is exactly
where diagnosis is hardest, and it is precisely the half that becomes invisible.
Trace context is therefore a required field of the job payload and the event
envelope, not an optional addition — the same treatment as tenant context, for
the same reason.

---

## Log Design

**Decision.** Structured logs only. No unstructured string logging anywhere.

| Field | Required | Notes |
| --- | --- | --- |
| `timestamp`, `level`, `message` | Yes | Message is a stable template, not interpolated content |
| `tenantId` | Yes where a tenant exists (O-3) | |
| `correlationId`, `traceId`, `spanId` | Yes | Log↔trace correlation |
| `service`, `version`, `environment` | Yes | |
| `context`, `module` | Yes | Which bounded context emitted it |
| Domain identifiers | As relevant | IDs, never content |

**Levels, with explicit meaning** — levels without agreed semantics degrade into
everything being `info`:

| Level | Meaning | Action |
| --- | --- | --- |
| `error` | An operation failed and a user or system is affected | Investigate; may alert |
| `warn` | Something unexpected that the system handled | Review in aggregate; trending warns matter |
| `info` | A significant business event occurred | Searchable record |
| `debug` | Diagnostic detail | Off in production; enabled per tenant temporarily |

**Never logged:** request or response bodies, artifact content, credentials,
tokens, PII beyond identifiers, `db.statement`. Enforced by a redacting logger
(D-79), because reviewer vigilance fails eventually and logs are retained,
replicated and broadly readable.

**Per-tenant debug logging** is a deliberate capability: support investigating a
specific tenant's issue can raise verbosity for that tenant alone, time-bounded
and audited, without flooding logs platform-wide.

---

## Metric Design and Cardinality

**Decision.** Metrics follow RED for services and USE for resources.
**`tenant.id` is a trace and log attribute, never a metric label.**

**Reasoning — and this is the most important decision in this document.** A
metric's cost and queryability are determined by its cardinality: the number of
distinct label combinations. At 50,000 tenants (S-1), adding `tenant_id` to a
single metric with 10 endpoints and 5 status codes produces 2.5 million time
series *for that one metric*. The backend cost becomes untenable and queries
become slow — and this is a failure that arrives suddenly at scale, having looked
fine with 50 tenants in staging.

Traces and logs are different: they are individual events, sampled and retained
briefly, where high-cardinality attributes are the entire point. The distinction
between "dimension on an event" and "label on an aggregate" is the one that
matters, and conflating them is the standard way observability bills explode.

**How per-tenant visibility is preserved without per-tenant labels:**

| Need | Mechanism |
| --- | --- |
| "Which tenants are slow?" | Traces filtered by `tenant.id` — high cardinality is fine on spans |
| Per-tenant SLO tracking | Metrics for the **top-N tenants by volume** only, bounded label set |
| Per-tenant cost (O-5) | Aggregated in the database from job records, not from metrics |
| Anomaly detection | Exemplars linking a metric bucket to representative traces |

**Standard metrics:**

| Kind | Metrics | Labels |
| --- | --- | --- |
| **RED** (services) | Rate, Errors, Duration | service, endpoint/job type, status |
| **USE** (resources) | Utilization, Saturation, Errors | resource, instance |
| **Queue** | Depth, age, throughput, DLQ count | queue name, priority |
| **Business** | Artifacts generated, approvals, AI cost, evaluation scores | type, workflow — **never tenant** |

---

## Sampling

**Decision.** Tail-based sampling at the collector, with mandatory retention of
error and slow traces.

**Reasoning.** Head-based sampling decides at trace start, before it is known
whether the trace is interesting — so it discards the errors and slow requests
that are the only traces anyone wants. Tail-based sampling decides after the
trace is complete, when its outcome is known.

| Trace class | Sampling |
| --- | --- |
| Errors | **100%** |
| Slow (over budget, `26`) | **100%** |
| AI generation | 100% — low volume, high value, cost-bearing |
| Normal reads | 1–5%, adaptive by volume |
| Health checks | 0% |

**Trade-offs.** Tail-based sampling requires buffering complete traces at the
collector, costing memory and adding a small delay before export. Worth it: the
alternative is discarding exactly the data that matters.

**Logs are not sampled** at `error` and `warn`. `info` is sampled under extreme
volume, `debug` is off by default.

---

## Alerting Architecture

Per `04` (O-4) and `15`: **alerts fire on SLO error-budget burn rate, not raw
thresholds.**

| Layer | Alerts on | Routing |
| --- | --- | --- |
| SLO burn rate | Fast burn (2% budget in 1h), slow burn (10% in 6h) | Page / ticket |
| Saturation | Queue age, connection pool, memory headroom | Page above threshold |
| Correctness | DLQ entries, outbox lag, isolation test failure | Page immediately |
| Security | Authorization denial spikes, JIT access, secret scan | Security channel |
| Cost | AI cost per artifact trending, tenant budget breach | Ticket |
| **Meta** | Telemetry absent (dead man's switch, D-305) | Page — via a separate path |

**Every alert has a runbook or it is deleted** (D-159). An alert nobody can act
on trains people to ignore alerts, which is how the actionable ones get missed.

**Correctness alerts page immediately regardless of budget** — a DLQ entry means
work was lost, and error budgets are about availability, not about silently
losing data.

---

## Cost Control

Full-fidelity telemetry at 5k rps can rival application infrastructure cost
(`08`). Controls, in order of impact:

1. **Cardinality limiting** at the collector — the dominant cost driver.
2. **Tail-based sampling** — retains value while discarding volume.
3. **Retention tiers** — traces 14 days, metrics 13 months, logs 30 days hot then
   archived to object storage.
4. **Log level discipline** — `debug` off, `info` sampled at extreme volume.
5. **Drop high-volume low-value signals** — health checks, static asset requests.

Telemetry cost is monitored as a line item, because an unmonitored observability
bill is the one that surprises.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-412 | OpenTelemetry across all runtimes, exported via a collector | One data model, one propagation standard, replaceable backend |
| D-413 | Collector is the enforcement point for redaction, sampling and cardinality | One place to enforce, rather than trusting every emitter |
| D-414 | Async work uses span links, not parent-child nesting | Nesting a 94-second job under a 180ms request destroys latency metrics |
| D-415 | Trace context is a required field of job payloads and event envelopes | The async half is where diagnosis is hardest and propagation is lost by default |
| D-416 | `db.statement` is never captured | Query text with parameters is tenant content in a widely-readable store |
| D-417 | **`tenant.id` is a trace and log attribute, never a metric label** | 50k tenants × endpoints × statuses is millions of series; the failure arrives suddenly at scale |
| D-418 | Per-tenant metrics limited to a bounded top-N set | Preserves the visibility that matters without unbounded cardinality |
| D-419 | Per-tenant cost aggregated from job records, not from metrics | Cost attribution needs exactness, which metrics cannot provide at this cardinality |
| D-420 | Tail-based sampling with 100% retention of errors and slow traces | Head-based sampling discards exactly the traces anyone wants |
| D-421 | Log message is a stable template; content goes in fields | Enables aggregation by message and keeps content out of the message string |
| D-422 | Per-tenant debug verbosity, time-bounded and audited | Supports investigation without flooding platform-wide logs |
| D-423 | Correctness alerts page immediately, independent of error budget | Error budgets govern availability, not silent data loss |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Metric cardinality explodes via a well-meaning label addition | Backend cost spike; slow queries | Cardinality limiting at the collector; label sets reviewed on metric changes |
| Trace context dropped at the queue boundary | Async work invisible; diagnosis reverts to guesswork | Required payload field; trace completeness checked in tests |
| Sensitive content reaches logs despite redaction | Confidentiality breach | Redaction at both application and collector; periodic log content audit |
| Telemetry cost grows faster than traffic | Unbudgeted spend | Cost monitored as a line item; sampling and retention tuned |
| Alert fatigue from non-actionable alerts | Real incidents missed | Runbook requirement; periodic alert review with deletion |
| Collector becomes a single point of telemetry failure | Blind during an incident | Per-node deployment, disk spill buffering, dead man's switch |
| Tail sampling buffer overflows under burst | Traces lost during the most interesting periods | Buffer sized for burst; overflow prefers keeping errors |

## Dependencies

- **Depends on:** NFRs (`04`), containers (`30`), communication (`34`), events
  (`35`).
- **Depended on by:** resilience (`41`), deployment (`15`).

## Future Improvements

- Add continuous profiling once traffic justifies the overhead (`26`).
- Publish the exemplar linking configuration so metric anomalies jump directly to
  representative traces.
- Define per-tenant SLO reporting for enterprise customers once contractual SLAs
  exist.
