# LLM Wiki - Operating Schema

This file defines the universal rules for a repo-local wiki stored in `kb/`.

At the start of each wiki task:
1. read this file
2. read `REPO_WIKI_SPEC.md`
3. read `wiki/index.md`
4. read recent entries in `wiki/log.md`

All paths below are relative to `kb/`.

---

## Role

You maintain a structured knowledge base for the current software repository.

Your job is to:
- ingest raw source documents into wiki pages
- keep pages consistent, cross-referenced, and up to date
- answer queries from the wiki first, not from memory
- file approved durable answers back into the wiki
- lint the wiki for contradictions, stale content, orphan pages, and missing cross-links

You never modify files in `raw/`. You own everything in `wiki/`.

---

## Structure

```text
raw/                    <- immutable source documents
wiki/
  index.md              <- master catalog of wiki pages
  log.md                <- append-only activity log
  overview.md           <- high-level synthesis of current coverage
  glossary.md           <- terminology and style conventions
  sources/              <- source pages
  analyses/             <- filed analysis pages
  <repo-category>/      <- repo-specific topic pages, one directory per topic category from REPO_WIKI_SPEC.md
```

The repo-specific topic categories and their order are defined in `REPO_WIKI_SPEC.md`.

---

## Frontmatter

All wiki pages use this shared frontmatter shape:

```yaml
---
title: <page title>
type: index | log | overview | glossary | source | topic | analysis
created: YYYY-MM-DD
updated: YYYY-MM-DD
sources: [list of raw source filenames that informed this page]
tags: [relevant tags]
category: <required for source, topic, analysis; omit for control pages>
---
```

Rules:
- `index`, `log`, `overview`, and `glossary` are control pages and omit `category`
- `source` pages use `category: sources`
- `analysis` pages use `category: analyses`
- `topic` pages use a repo-defined category from `REPO_WIKI_SPEC.md`
- directory name must match `category`

---

## Page Shapes

### Shared content pages

For `source`, `topic`, and `analysis` pages:
1. one-line summary near the top
2. structured body with headings as needed
3. `## Related Pages` at the bottom with `[[wikilinks]]`

### `wiki/index.md`

Required sections:
1. short purpose statement
2. `## How to Read This Index`
3. `## Core Files`
4. `## Sources`
5. one section for each active topic category, in repo-spec order
6. `## Analyses`
7. `## Index Maintenance Notes`

### `wiki/log.md`

Entries are append-only and oldest to newest.

Entry format:

```text
## [YYYY-MM-DD] <event-type> | <summary>

Pages created: ...
Pages updated: ...
Key notes: ...
```

### `wiki/overview.md`

Keep this as the current synthesis of what the wiki covers, what is missing, and what questions remain open.

### `wiki/glossary.md`

Keep this as the canonical source for terminology and style conventions used across the wiki.

---

## Workflows

### Ingest

When the user says `ingest [source]`:
1. read the source from `raw/`
2. surface key takeaways before writing
3. create or update a page in `wiki/sources/`
4. update any affected topic pages
5. update `wiki/glossary.md` if terminology changes
6. update `wiki/index.md`
7. update `wiki/overview.md` if the big picture changes
8. append to `wiki/log.md`

### Query

When the user asks a wiki question:
1. read `wiki/index.md`
2. read the relevant pages
3. answer from the wiki with citations
4. ask whether the answer should be filed
5. if approved, save it under `wiki/analyses/`
6. append to `wiki/log.md`

### Update

When wiki content needs to change:
1. read the current page or pages in full
2. identify downstream effects and contradictions
3. show the proposed change and its source
4. apply approved updates
5. update index, overview, and log when needed

### Lint

When the user asks to lint the wiki:
1. read the wiki pages
2. report contradictions, stale claims, orphan pages, broken links, missing cross-links, and category pressure
3. propose fixes, then apply approved ones
4. append to `wiki/log.md`

---

## Cross-References

- use `[[filename-without-extension]]` for internal links
- add backlinks where they improve navigation or understanding
- prefer linking to existing pages over repeating the same explanation in multiple places

---

## Terminology

- check `wiki/glossary.md` before introducing or changing important terms
- if a source conflicts with existing glossary usage, flag it explicitly
- prefer the glossary's canonical term across the wiki

---

## ASCII And Encoding

- prefer plain ASCII wherever possible
- use `->` instead of Unicode arrows
- use `-` instead of en dash or em dash
- use straight quotes
- avoid emoji and decorative Unicode
- keep filenames ASCII kebab-case
- use non-ASCII only when it is necessary for source fidelity

These rules exist because non-ASCII punctuation has repeatedly caused mojibake in this environment.

---

## Notes

- keep this file universal
- keep repo-local details in `REPO_WIKI_SPEC.md`
- keep detailed operator steps in the wiki skills, not here
