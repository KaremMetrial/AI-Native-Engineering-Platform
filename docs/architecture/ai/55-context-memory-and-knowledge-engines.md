# Context, Memory and Knowledge Engines

## Purpose

Specify the three engines that decide *what the model sees*. They are routinely
conflated into "RAG" and are genuinely distinct: the Context Engine assembles a
single request, the Memory Engine persists state across requests, and the
Knowledge Engine curates the corpus both draw from.

Grounding quality is the platform's differentiator (`10`). These three engines
are where it is produced or lost.

## Scope

**In scope:** the separation of the three engines, context assembly, memory
tiers and their write governance, knowledge sources and trust tiers, retrieval
strategy, and freshness.

**Out of scope:** the storage layer (`36`), prompt composition (`52`), evaluation
(`57`).

---

## Why Three Engines

**Decision.** Context, memory and knowledge are separate subsystems with separate
lifetimes, trust models and write paths.

| | **Context Engine** | **Memory Engine** | **Knowledge Engine** |
| --- | --- | --- | --- |
| **Lifetime** | One request | Session to permanent | Permanent, curated |
| **Owns** | Nothing — it selects | Derived facts about work | Indexed source material |
| **Written by** | Never written | Workflows, with governance | Ingestion pipeline |
| **Authority** | None | **Advisory only** | Tiered by source |
| **Failure mode** | Wrong context selected | **Poisoned by a false memory** | Stale or mis-attributed |
| **Rebuildable** | N/A | Yes — from source | Yes — from source |

**Reasoning.** Collapsing them produces the failure each is designed to prevent.
A single "context store" that accumulates model outputs becomes an unversioned,
unattributed pool where a hallucinated fact is indistinguishable from an approved
requirement — and once retrieved as grounding, it compounds through every
subsequent generation. That is the mechanism by which an AI system degrades
quietly over months.

Separating them means each has an explicit trust level, an explicit write path,
and an explicit rebuild path.

**Alternatives.** *One unified vector store* — simplest, and loses provenance,
trust tiering and the distinction between authoritative and derived. *No memory
at all* — safest, and forces users to re-establish context every session, which
is the stateless-chat experience the platform exists to improve on. *Memory as
authoritative* — powerful and makes model output authoritative without approval,
contradicting D-36.

**Trade-offs.** Three subsystems to build, and assembly must reconcile material
from all three within one token budget — including handling contradiction between
them.

**Benefits.** Every piece of context carries a trust level and a provenance. A
false memory cannot masquerade as an approved requirement.

**Long-term impact.** This separation is what keeps grounding quality from
decaying as the corpus grows. Systems that conflate the three degrade in a way
that is very hard to diagnose, because the symptom — gradually worse output — has
no single cause to find.

---

## Context Engine

Per-request assembly. Deepened from `40`, with the reconciliation rules that
document did not specify.

```
   REQUEST  workflow · target artifact · tenant · token budget
        │
   1  STRUCTURAL     graph traversal — what this derives from
                     APPROVED VERSIONS ONLY (D-91) · depth-bounded
        │
   2  MEMORY         relevant working/session/entity memory (below)
                     tagged: advisory
        │
   3  KNOWLEDGE      retrieval over the curated corpus (below)
                     tagged with its trust tier
        │
   4  RECONCILE      contradiction detection across sources
        │
   5  RANK           structural ≫ knowledge ≫ memory
        │
   6  BUDGET         trim by rank; untrusted capped separately (D-582)
        │
   7  ASSEMBLE       stable prefix first (D-581); each block attributed
        │
   RESULT  context blocks + retrieval manifest → lineage (D-448)
```

### Reconciliation

**Decision.** When sources contradict, the higher-authority source wins and the
contradiction is surfaced in the prompt rather than silently resolved.

**Reasoning.** Contradiction is common and informative: an entity memory says the
tenant uses fixed-price contracts while an approved requirement specifies time
and materials. Silently dropping one produces confidently wrong output with no
trace of the conflict. Including both with their authority levels lets the model
note the discrepancy — and lets the reviewer see it, which is often the most
valuable thing the generation produces.

**Authority order:** approved artifacts → curated platform knowledge → tenant
reference material → memory. Memory ranks last deliberately: it is derived,
revisable and the most likely to be wrong.

### Ranking

Structural context outranks everything (D-90). Knowing this SRS derives from
*these three approved requirements* is exact; semantically similar passages are
approximate. This is the graph paying for itself in AI quality, and it is the
grounding advantage a competitor without a Delivery Graph cannot replicate.

**Every context block is attributed** in the assembled prompt — source, trust
tier, version. This is what allows the model to cite, the reviewer to verify, and
the lineage record to explain the output years later.

---

## Memory Engine

The genuinely new subsystem, and the one with the sharpest failure mode.

### Tiers

| Tier | Lifetime | Contents | Written by |
| --- | --- | --- | --- |
| **Working** | One workflow run | Step outputs, intermediate reasoning, scratchpad | Workflow engine, automatically |
| **Session** | A user's working session | Recent interactions, stated intent, corrections | Workflow, automatically |
| **Entity** | Persistent, per project or tenant | Durable facts: conventions, preferences, terminology, recurring constraints | **Governed — see below** |

**Working and session memory are ephemeral and low-risk**: they expire, they are
scoped to one run or one session, and a mistake in them affects one interaction.

**Entity memory is durable and high-risk**, and is where the governance applies.

### Memory poisoning — the failure this engine must prevent

**Decision.** A durable entity memory may be written only from an **approved
artifact**, an **explicit user statement**, or a **model inference that a human
has confirmed**. A model may never write durable memory unilaterally.

**Reasoning.** This is the single most important decision in this document. If a
workflow can write "this tenant uses SAFe" into durable memory because a model
inferred it from ambiguous discovery notes, that inference becomes grounding for
every future generation for that tenant. It will be retrieved, reinforced, and
eventually cited as established fact — by which point its origin is untraceable
and its correction requires knowing it was wrong in the first place.

A single hallucination promoted to durable memory becomes a permanent, invisible
distortion of every subsequent output. There is no error, no alert, and no
natural mechanism by which anyone discovers it.

This is D-36 applied to the system's own state: **AI produces drafts; human
approval confers authority** — including authority over what the system believes.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **Free model-written memory** | Maximum adaptivity; the poisoning failure above, silently and permanently |
| **No durable memory** | Immune to poisoning; loses the compounding tenant-specific value that makes the platform improve with use |
| **Model writes with confidence threshold** | Feels principled; confidence is poorly calibrated and a confident hallucination is exactly the failure case |
| **Governed writes** *(chosen)* | Durable value with a bounded trust model |

**Trade-offs.** Slower memory accumulation, and a review burden on memory
candidates. Some genuinely useful inferences will not be captured because nobody
confirmed them.

**Benefits.** Durable memory is trustworthy enough to rank as grounding.
Corrections are possible because provenance is recorded.

**Long-term impact.** Entity memory compounds over years — a tenant's accumulated
conventions are exactly the asset that makes the platform stickier over time. That
compounding is only valuable if the contents are true; a compounding store of
half-true inferences is a liability that grows.

### Memory record

| Field | Purpose |
| --- | --- |
| `scope` | tenant · project · user |
| `assertion` | The fact, structured where possible |
| `provenance` | Source artifact version, user statement, or confirmation record |
| `confidence` | Recorded, but never a substitute for provenance |
| `confirmed_by`, `confirmed_at` | Who conferred authority |
| `supersedes` | Prior contradicting memory |
| `last_used_at`, `use_count` | Decay and pruning |
| `tenant_id` | RLS-enforced; memory is tenant content (`44`) |

### Contradiction and decay

- **Contradiction** creates a supersession, never a silent overwrite — the prior
  memory is retained with its provenance so a wrong correction is recoverable.
- **Decay:** memories unused for a long period are demoted in ranking before
  being pruned. A convention that stopped applying should fade rather than
  persist forever.
- **Correction is user-facing**: tenants can view and edit what the system
  believes about them. A memory store users cannot inspect is one they cannot
  correct, and inspectability is what makes it trustworthy.

---

## Knowledge Engine

The curated corpus, with trust tiers that travel with retrieved material.

### Sources and trust tiers

| Tier | Source | Trust | Attribution in prompt |
| --- | --- | --- | --- |
| **1 · Authoritative** | Tenant's approved artifacts (via the graph) | Highest | "Approved requirement v3" |
| **2 · Platform knowledge** | Curated methodology: estimation heuristics, architecture patterns, requirement templates | High, general | "Platform guidance" |
| **3 · Tenant reference** | Tenant-uploaded standards, style guides, prior contracts | Medium — tenant-owned, unvalidated | "Tenant reference material" |
| **4 · Untrusted input** | Client-supplied documents in the current work | **None** | `UntrustedBlock` (D-575) |

**Decision.** Tiers are never blended without attribution.

**Reasoning.** A generation drawing on all four should be able to distinguish
them, and so should the reviewer. Presenting platform guidance and an approved
tenant requirement as undifferentiated context invites the model to treat general
advice as binding constraint, or a client's aspiration as an approved decision.
Attribution is what makes the output verifiable — the reviewer can check the
claim against its stated source.

**Only approved artifacts are indexed as tier 1** (D-91, D-446): indexing drafts
would let unapproved content ground future generations, compounding error through
the graph.

### Ingestion pipeline

```
   Source change event
        │
   1  EXTRACT      text from the source format
   2  CHUNK        semantic boundaries — sections, requirements (D-447)
   3  ENRICH       attach trust tier, provenance, tenant, version
   4  EMBED        fast tier, batched, model id recorded (D-392)
   5  INDEX        vector + keyword, tenant-partitioned
```

**Chunking on semantic boundaries** rather than fixed windows: a requirement
split across two chunks retrieves poorly in both halves and is partially
meaningless in each.

### Retrieval strategy

**Decision.** Hybrid retrieval — graph traversal, vector similarity and keyword
matching — with fusion and reranking, rather than vector search alone.

**Reasoning.** Pure vector retrieval fails predictably on exact identifiers,
rare terminology and negation. A search for requirement "REQ-142" or for a
specific client name is a keyword problem; semantic similarity returns things
that are *about* similar topics and misses the exact match. Conversely, keyword
search misses paraphrase, which is most of natural language.

Graph traversal outranks both where it applies, because it is exact rather than
approximate.

**Fusion:** results from each method are merged with rank-aware weighting, then
reranked against the actual query. **Tenant filtering is applied within each
retrieval method before fusion** (D-23) — never after, and never only at fusion,
which would let one method surface another tenant's content before the filter.

**Retrieval is deterministic** given the same corpus state and query: stable sort
with a deterministic tiebreak, so context assembly stays reproducible (D-579).

### Freshness

| Trigger | Action |
| --- | --- |
| Artifact approved | Index and embed |
| Artifact superseded | Reindex; prior version demoted, not deleted |
| Artifact deleted | Remove via tombstone (D-509) |
| Embedding model change | **Planned re-embedding migration** (D-393) |
| Platform knowledge updated | Versioned and reindexed; version recorded in lineage |

**Platform knowledge is versioned like a prompt.** It shapes output as directly
as an instruction does, so a change to estimation heuristics is a behaviour change
and passes through `53`'s pipeline.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-613 | Context, memory and knowledge are three engines with distinct trust and write paths | Collapsing them makes a hallucinated fact indistinguishable from an approved requirement |
| D-614 | Source contradictions are surfaced in the prompt, not silently resolved | Silent resolution produces confidently wrong output; the conflict is often the most useful finding |
| D-615 | Authority order: approved artifacts → platform knowledge → tenant reference → memory | Memory is derived, revisable and most likely to be wrong |
| D-616 | Every context block is attributed with source, tier and version | Enables citation, reviewer verification and lineage explicability |
| D-617 | **Durable entity memory requires approved artifact, explicit user statement, or confirmed inference** | A single hallucination promoted to durable memory permanently distorts all future output, invisibly |
| D-618 | Confidence scores are recorded but never substitute for provenance | Confidence is poorly calibrated; a confident hallucination is the failure case |
| D-619 | Memory contradictions create supersessions, never overwrites | A wrong correction must be recoverable |
| D-620 | Unused memory decays in ranking before pruning | A convention that stopped applying should fade, not persist |
| D-621 | Memory is user-inspectable and user-correctable | A store users cannot inspect is one they cannot correct |
| D-622 | Knowledge tiers are never blended without attribution | Otherwise general guidance reads as binding constraint |
| D-623 | Only approved artifacts are indexed as authoritative | Indexing drafts compounds error through the graph |
| D-624 | Hybrid retrieval — graph, vector and keyword — with fusion and reranking | Vector search fails on exact identifiers, rare terms and negation |
| D-625 | Tenant filtering applied within each retrieval method before fusion | Filtering at fusion lets one method surface other tenants' content first |
| D-626 | Retrieval is deterministic given corpus state and query | Context assembly reproducibility depends on it |
| D-627 | Platform knowledge is versioned and deployed like a prompt | It shapes output as directly as an instruction |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Memory poisoning despite governance | Permanent invisible quality distortion | Provenance mandatory; memory inspectable and correctable; memory ranks lowest |
| Memory governance friction stops memory accumulating | The compounding value never materializes | Confirmation surfaced as a low-friction step in existing review flows, not a separate chore |
| Retrieval returns plausible but irrelevant material | Quality degradation; wasted tokens | Structural context ranked first; retrieval precision measured in evaluation (`57`) |
| Trust tier lost during assembly | Guidance treated as requirement | Tier is a structural property of the context block, not a formatting convention |
| Knowledge corpus grows stale | Grounding on superseded material | Event-driven reindexing; supersession demotes rather than deletes |
| Hybrid retrieval latency exceeds budget | P-6 breach | Methods run in parallel; per-method timeouts with partial fusion |
| Contradiction surfacing overwhelms the prompt | Token waste, model confusion | Only material contradictions surfaced, above a relevance threshold |

## Dependencies

- **Depends on:** AI strategy (`10`), AI integration (`40`), data architecture
  (`36`, `42`), prompt engine (`52`).
- **Depended on by:** workflows and agents (`56`), evaluation (`57`), AI security
  (`58`).

## Future Improvements

- Add per-tenant retrieval quality measurement so grounding can be tuned per
  tenant as their corpus grows.
- Evaluate learned reranking once sufficient relevance-feedback data exists.
- Add memory candidate suggestion into the artifact approval flow, so
  confirmation happens where the user already is.
