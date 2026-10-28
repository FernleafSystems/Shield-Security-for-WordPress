---
title: Glossary
type: glossary
created: 2026-04-07
updated: 2026-10-28
sources: []
tags: [glossary, terminology, style]
---

# Glossary

Canonical reference for terminology and style conventions used across this wiki.

---

## How to Read This Glossary

Preferred entry shape:

```text
Term (canonical form)
: Definition. Usage notes. Related terms.
- Preferred: `term`
- Avoid: `deprecated term`
- See also: [[related-page]]
```

---

## Terminology

Visitor standing
: The single per-IP judgement (Trusted, Normal, Doubtful, Hostile, Blocked) worked out from weighted offences, CrowdSec, bot signals and identity. Replaces the offence-limit auto-block as the basis for IP decisions.
- Preferred: `visitor standing`, `standing`
- Avoid: `risk band`, `policy state`
- See also: [[visitor-standing-design]]

Request sensitivity
: How much harm a request could do (Open, Guarded, Critical), judged by what it touches rather than its HTTP method.
- Preferred: `request sensitivity`
- Avoid: `surface`, `read vs mutation`
- See also: [[visitor-standing-design]]

Proof of humanity
: A fresh silentCAPTCHA (notbot) or AltCha signal with a bot score at or above the silentCAPTCHA minimum, or a logged-in session. Satisfies a challenge.
- Preferred: `proof of humanity`
- See also: [[visitor-standing-design]]

---

## Style Conventions

This section can hold wiki-maintenance conventions and source-driven style rules as they are ingested.

| Convention | Rule | Example |
|---|---|---|
| ASCII-first punctuation | Prefer plain ASCII punctuation in maintained wiki files | use `->` instead of a Unicode arrow |
| Filenames | Use ASCII kebab-case filenames | `login-guard-overview.md` |
| Internal links | Use wikilinks without extension | `[[overview]]` |

---

## Deprecated Or Avoided Terms

Terms that should be replaced, renamed, or avoided:

| Avoid | Use Instead | Reason |
|---|---|---|
| smart punctuation by default | plain ASCII punctuation | reduces mojibake risk in this environment |

---

## Variants And Notes

Track important term variants, deprecated names, or audience-specific wording here when needed.

---

## Related Pages

- [[overview]] - high-level synthesis of wiki coverage
- [[index]] - master catalog of wiki pages
- [[log]] - chronological record of wiki activity
