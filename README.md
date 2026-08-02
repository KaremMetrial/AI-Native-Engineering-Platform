# AI-Native Engineering Platform

An AI-native SaaS platform built to enterprise, multi-tenant, cloud-native
standards.

## Engineering Charter

Every change to this repository — human-authored or AI-authored — is governed
by the **[Master System Prompt](docs/engineering/MASTER_SYSTEM_PROMPT.md)**. It
defines the operating role, the quality bar, the principles to prefer, and the
rules that are never negotiable.

Read it before contributing. It is the standard code review is conducted
against.

AI coding agents pick it up automatically: [`CLAUDE.md`](CLAUDE.md) imports it
at the repository root.

## Repository Layout

| Path                                        | Purpose                                        |
| ------------------------------------------- | ---------------------------------------------- |
| `CLAUDE.md`                                 | Agent entry point; imports the charter.        |
| `docs/engineering/MASTER_SYSTEM_PROMPT.md`  | The engineering charter. Canonical, versioned. |

## Changing the Charter

The charter is versioned. Any amendment must, in a single commit:

1. Update the text.
2. Bump the version in the metadata table.
3. Add a row to the revision history explaining the change.
