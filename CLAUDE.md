# CLAUDE.md

## Engineering Charter

The engineering charter below is binding for every change made to this
repository. Read it before planning or writing any code.

@docs/engineering/MASTER_SYSTEM_PROMPT.md

## Repository Conventions

The charter is the single source of truth for standards. This section carries
only what is specific to this repository and cannot be derived from the
charter.

- The charter lives at `docs/engineering/MASTER_SYSTEM_PROMPT.md` and is
  imported here. Change it in that file only — never restate its rules
  elsewhere, in this file or in code comments.
- Any change to the charter is a versioned change: bump the version in its
  metadata table and add a row to its revision history in the same commit.
- Build, test, and lint commands are documented here as each part of the
  platform lands, alongside the code that introduces them. A command is
  documented only once it exists and passes.
