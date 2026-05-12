# Repo Wiki Spec

This file defines the repo-local configuration for the wiki in `kb/`.

---

## Repo

- Name: WP Plugin Shield
- Purpose: store durable repo knowledge derived from imported raw documents
- Raw source intake: import new source documents into `kb/raw/` before running wiki ingest

---

## Active Topic Categories

| Category | Directory | Used For | Order |
|---|---|---|---|
| features | `wiki/features/` | feature-oriented topic pages | 1 |
| products | `wiki/products/` | product or tool topic pages | 2 |
| plans | `wiki/plans/` | intended future work and execution design pages worth revisiting | 3 |
| concepts | `wiki/concepts/` | conceptual topic pages | 4 |
| style | `wiki/style/` | style-rule topic pages | 5 |

---

## Notes

- Keep categories minimal until the wiki shows pressure for a new one.
- `sources` and `analyses` are universal categories and do not need to be listed here.
- If a new topic category is needed, add it here first, then update `wiki/index.md` and use it consistently.
