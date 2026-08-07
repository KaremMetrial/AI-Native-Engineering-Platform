# Prompt Engine and Prompt Library

## Purpose

Specify how prompts are represented, composed, rendered and curated. The Prompt
Engine turns structured prompt assets plus runtime context into a
provider-specific request, deterministically. The Prompt Library is the governed
collection of reusable assets it draws on.

## Scope

**In scope:** prompt representation, composition model, deterministic rendering,
token budgeting, provider adaptation, and the library's structure, reuse rules
and lifecycle.

**Out of scope:** the prompt lifecycle process (`53`), runtime monitoring (`54`),
context assembly (`55`).

---

## Prompts Are Structured Objects, Not Strings

**Decision.** A prompt is a typed, composable object graph. String concatenation
to build prompts is prohibited.

**Reasoning.** A prompt-as-string cannot do four things the platform requires:

1. **Compose safely.** Concatenating a shared preamble, tenant conventions,
   retrieved context and untrusted user content produces a flat string in which
   the boundary between instruction and data is a formatting convention. That
   boundary is a security control (`58`), and a convention is not enforceable.
2. **Support prefix caching.** The stable/volatile boundary must be *declarable*
   for the gateway to place cache breakpoints or guarantee identical prefixes
   (D-556). In a string, that boundary is invisible.
3. **Adapt per provider.** System instructions go in a separate parameter, a role
   message or an instruction field depending on provider (`50`). A pre-assembled
   string has already made that choice, wrongly, for two of the three.
4. **Diff meaningfully.** A structured change — one fragment revised — is
   reviewable. A string diff across a 4,000-token prompt is not.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **Template strings with interpolation** | Familiar and fast; loses the instruction/data boundary, the cache boundary and provider adaptability |
| **Prompt-as-code (functions returning strings)** | Flexible and expressive; unreviewable by non-engineers, untestable in isolation, and non-deterministic by accident |
| **External prompt-management SaaS** | Versioning and UI editing for free; puts our most behaviour-defining asset in a third party, permits ungoverned live edits (contra D-93), and adds a dependency to the core capability |
| **Structured composable objects** *(chosen)* | Enforceable boundaries, declarable cache regions, provider-adaptable, reviewable |

**Trade-offs.** More machinery than string templates. Authors work with a
structure rather than free text, which is a learning cost and constrains
expression.

**Benefits.** The instruction/data boundary becomes structural rather than
textual. Caching, provider adaptation and review all become possible.

**Long-term impact.** Prompts are the fastest-churning behaviour-defining
artifact in the system (`23`). A representation that supports composition and
review is what keeps that churn governed rather than chaotic.

### Prompt structure

```
   PromptDefinition
   ├── metadata          id, version, owner, workflow, status
   ├── requires          declared capabilities (D-554)
   ├── inputs            typed variable schema — validated before render
   ├── output_contract   JSON schema the response must satisfy
   │
   └── segments[]        ordered, each typed and each with a cache class
       ├── SystemFragment      role, safety preamble, output discipline
       ├── OrganizationFragment tenant conventions, terminology, standards
       ├── TaskInstruction     what to do
       ├── ExampleSet          few-shot exemplars
       ├── ContextBlock        retrieved grounding (`55`)
       ├── UntrustedBlock      client-supplied content — DELIMITED, LABELLED
       └── OutputDirective     schema restatement, format constraints
```

**`UntrustedBlock` is a distinct segment type, not a formatting convention.** It
renders with provider-appropriate delimiting and labelling, it is never merged
with instruction segments, and its presence flags the call for constrained tool
access (`58`). Making it a type means the engine — not the prompt author —
guarantees the boundary.

**Every segment carries a cache class** — `stable`, `semi_stable` or `volatile` —
which drives ordering and cache breakpoint placement (below).

---

## Composition

Segments are assembled from library assets plus runtime inputs.

| Mechanism | Purpose |
| --- | --- |
| **Inclusion** | A definition includes shared fragments by reference and version |
| **Override** | A workflow may override an included fragment, recorded explicitly |
| **Conditional segments** | Included based on typed input predicates — never on free-form logic |
| **Fragment parameters** | Fragments accept typed parameters, so one fragment serves several workflows without copying |

**Composition, never copy-paste.** A shared instruction duplicated across eight
workflows is duplicated *knowledge* (P11's boundary case): when it is corrected,
seven copies stay wrong. Fragments are the extraction mechanism, and the review
rule is that identical instruction text appearing in two definitions must become
a fragment.

**Conditional logic is deliberately weak** — predicates over typed inputs, no
arbitrary expressions. A prompt whose content depends on computed logic becomes
untestable, because the set of prompts it can produce is unbounded. Weak
conditionals keep that set enumerable, which is what makes `53`'s testing
possible.

---

## Deterministic Rendering

**Decision.** Rendering is a pure function: the same definition version plus the
same inputs produces byte-identical output, every time.

**Reasoning.** Three things depend on this, and all three fail silently without
it:

- **Response caching** (`51`) keys on the rendered prompt hash. Non-deterministic
  rendering means cache misses that should have been hits — a silent cost
  increase with no error anywhere.
- **Provider prefix caching** requires byte-identical prefixes (D-557). A single
  varying byte forfeits the largest cost lever we have.
- **Reproducibility.** A generated artifact must be explicable years later
  (U-4). If the prompt cannot be reconstructed exactly, the lineage record points
  at something that can no longer be produced.

**Sources of non-determinism, all eliminated at the engine:**

| Source | Handling |
| --- | --- |
| Map/set iteration order | Canonical ordering enforced on serialization |
| Timestamps, random IDs | Prohibited in segments; injected only as declared inputs |
| Locale-dependent formatting | Fixed locale for rendering |
| Floating-point formatting | Canonical representation |
| Retrieval result ordering | Stable sort with a deterministic tiebreak (`55`) |
| Whitespace and JSON key order | Canonicalized |

**Where non-determinism is genuinely required** — a prompt legitimately
containing the current date — the input is declared, and the engine **marks the
call uncacheable** (D-570) rather than producing a key that will never hit.

---

## Ordering for Cache Efficiency

Segments render in cache-class order: stable first, volatile last.

```
   [ stable ]      SystemFragment · OrganizationFragment · ExampleSet
                   ↑ identical across many calls — the cacheable prefix
   [ semi ]        TaskInstruction · OutputDirective
   [ volatile ]    ContextBlock · UntrustedBlock · runtime inputs
```

**This ordering is a cost decision** (D-100, D-325). Tenant conventions,
templates and standards are identical across hundreds of calls; placing them
first means that large prefix is cached. Reversing the order — putting the
specific artifact first because it feels most important — forfeits the entire
saving while producing an identical-looking prompt.

The engine emits the stable/volatile boundary to the gateway, which realizes it
in each provider's caching mechanism (D-556).

---

## Token Budgeting

Composition is budget-aware, because a prompt that exceeds the model's context
window fails at dispatch, after all assembly work is done.

```
   budget = model.context_window − reserved_output − safety_margin

   allocate:  stable segments        (fixed, must fit)
              instruction segments   (fixed, must fit)
              context block          ← the flexible allocation
              untrusted block        (capped independently)

   if context exceeds its allocation → Context Engine trims by rank (`55`)
   if fixed segments exceed budget   → hard failure, clear reason
```

**Untrusted content is capped independently of retrieved context.** Without a
separate cap, a large uploaded document could consume the entire context
allocation and crowd out the graph-derived grounding that makes output good —
degrading quality in exactly the case (a big client brief) where quality matters
most.

**Fixed-segment overflow is a hard failure, not a silent trim.** Trimming
instructions or the output contract to fit produces a model call missing its
constraints, which fails in ways that look like model error.

---

## Provider Adaptation

Rendering targets a provider at the last step, after composition:

| Concern | Adaptation |
| --- | --- |
| System instruction | Separate parameter, first message, or instruction field per provider (`50`) |
| Untrusted delimiting | Provider-appropriate delimiters and labelling conventions |
| Few-shot format | Message pairs or inline examples per provider convention |
| Output contract | Native structured-output schema where supported; instruction-level restatement where not |
| Tool declarations | Canonical tool schema translated per provider |
| Cache breakpoints | Explicit markers, prefix guarantee, or managed context object (D-556) |

**Adaptation happens after composition, never during.** Composing
provider-specific text would make a definition single-provider, defeating both
the abstraction and cross-provider evaluation (`57`).

---

## The Prompt Library

The curated collection of reusable assets, versioned in the repository (D-93).

```
   prompts/
   ├── fragments/
   │   ├── system/            role, safety preamble, output discipline
   │   ├── organization/      tenant convention templates
   │   └── domain/            shared domain instruction fragments
   ├── workflows/
   │   └── <workflow>/        definitions, examples, output schemas
   ├── schemas/               output contracts, shared
   ├── examples/              curated few-shot sets
   └── rubrics/               evaluation rubrics (`57`) — colocated deliberately
```

**Rubrics live beside prompts, not in the test tree.** A prompt and the criteria
it is judged against are one artifact: changing what a prompt should produce
without changing how it is judged is how evaluation silently stops measuring the
right thing.

### Asset governance

| Property | Rule |
| --- | --- |
| **Ownership** | Every asset has an owner; unowned assets are removed (P13) |
| **Reuse** | By inclusion with a version, never by copying |
| **Naming** | Per `25`; version is part of the identity (D-254) |
| **Deprecation** | Assets are marked deprecated with a replacement, then removed once no definition references them |
| **Dependency** | The library knows which definitions include which fragments — a fragment change lists its blast radius |

**The dependency graph is what makes fragment reuse safe.** Changing a shared
safety preamble touches every workflow that includes it, and the author must see
that before merging — otherwise a small correction becomes an unreviewed
behaviour change across the platform.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-574 | Prompts are structured objects; string concatenation is prohibited | Strings cannot express the instruction/data boundary, the cache boundary, or provider adaptation |
| D-575 | `UntrustedBlock` is a segment type, enforced by the engine | A formatting convention is not a security control |
| D-576 | Every segment declares a cache class | The stable/volatile boundary must be declarable for prefix caching to work |
| D-577 | Reuse by fragment inclusion; identical instruction text in two definitions must be extracted | Duplicated instructions are duplicated knowledge — corrections leave copies wrong |
| D-578 | Conditional logic limited to predicates over typed inputs | Arbitrary logic makes the set of producible prompts unbounded and untestable |
| D-579 | Rendering is a pure function producing byte-identical output | Response caching, prefix caching and reproducibility all fail silently without it |
| D-580 | Enumerated non-determinism sources eliminated at the engine | Each one silently degrades caching with no error surfaced |
| D-581 | Segments render stable-first for prefix caching | Reversing the order forfeits the saving while producing an identical-looking prompt |
| D-582 | Untrusted content capped independently of retrieved context | Otherwise a large upload crowds out the grounding that makes output good |
| D-583 | Fixed-segment budget overflow fails hard; never trimmed | A call missing its instructions or output contract fails in ways that look like model error |
| D-584 | Provider adaptation occurs after composition, never during | Provider-specific composition makes a definition single-provider and blocks cross-provider evaluation |
| D-585 | Evaluation rubrics colocated with prompts | A prompt and its judging criteria are one artifact |
| D-586 | The library maintains a fragment dependency graph showing blast radius | A shared fragment change is an unreviewed platform-wide behaviour change otherwise |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Authors bypass structure with a large free-text segment | Boundaries and caching degrade | Segment types are constrained; review checks for instruction text in context segments |
| Rendering non-determinism introduced by a new segment type | Silent cache degradation | Determinism asserted by test — same inputs rendered twice must be byte-identical |
| Fragment reuse creates over-coupling across workflows | One change destabilizes many | Dependency graph makes blast radius visible; fragments kept small and single-purpose |
| Budget allocation starves context on small-window models | Quality degradation on some routes | Allocation computed per model; workflows declare minimum viable context |
| Library grows unmanaged | Duplicate and stale assets | Ownership required; unreferenced assets removed |
| Provider adaptation diverges in a way that changes meaning | Cross-provider quality differences attributed to models rather than adaptation | Conformance suite (D-561) covers adaptation; cross-provider evaluation isolates it |

## Dependencies

- **Depends on:** multi-provider (`50`), gateway (`51`), AI strategy (`10`),
  naming (`25`).
- **Depended on by:** PromptOps (`53`, `54`), context and memory (`55`),
  workflows and agents (`56`), evaluation (`57`), AI security (`58`).

## Future Improvements

- Add a prompt authoring linter enforcing segment discipline and flagging
  duplicate instruction text.
- Publish the fragment dependency graph as a reviewable artifact in pull
  requests, so blast radius appears in the diff.
- Add per-model rendering snapshots to catch adaptation regressions.
