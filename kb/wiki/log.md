---
title: Wiki Log
type: log
created: 2026-04-07
updated: 2026-04-21
sources: []
tags: [log, history]
---

# Wiki Log

Append-only chronological record of wiki activity.

Use this file to understand recent wiki changes before making new ones.

---

## [2026-04-07] init | Wiki created

Pages created: `wiki/index.md`, `wiki/log.md`, `wiki/overview.md`, `wiki/glossary.md`
Pages updated: none
Key notes: Initialized self-contained wiki structure under `kb/`.

## [2026-04-21] schema | Normalize wiki instructions and control page contracts

Pages created: none
Pages updated: `AGENTS.md`, `wiki/index.md`, `wiki/log.md`, `wiki/overview.md`, `wiki/glossary.md`
Key notes: Replaced copied-template wording, made `index` and `log` first-class page types, preserved raw-only ingest, and added ASCII-first maintenance rules.

## [2026-04-21] schema | Split universal schema from repo-local spec

Pages created: `REPO_WIKI_SPEC.md`
Pages updated: `AGENTS.md`, `wiki/index.md`, `wiki/log.md`, `wiki/overview.md`, `wiki/glossary.md`
Key notes: Moved repo-local category configuration into `REPO_WIKI_SPEC.md`, simplified the universal schema, and adopted portable `type` plus `category` rules for future content pages.

## [2026-04-21] schema | Tighten repo spec split

Pages created: none
Pages updated: `AGENTS.md`, `REPO_WIKI_SPEC.md`, `wiki/index.md`
Key notes: Kept `sources` and `analyses` as universal categories, limited `REPO_WIKI_SPEC.md` to topic categories only, and simplified the portable model further.

## [2026-04-21] schema | Replace personas with plans

Pages created: none
Pages updated: `REPO_WIKI_SPEC.md`, `wiki/index.md`, `wiki/log.md`
Key notes: Dropped `personas` from the repo topic categories and added `plans` for intended future work and execution design pages.
