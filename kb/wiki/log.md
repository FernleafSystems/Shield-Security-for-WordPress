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

## [2026-08-21] analysis | Research WordPress 7.1 plugin compatibility

Pages created: `wiki/analyses/wordpress-7-1-plugin-compatibility.md`
Pages updated: `wiki/index.md`, `wiki/log.md`, `wiki/overview.md`
Key notes: Recorded official WordPress 7.1 compatibility changes, a vendor-confirmed WP Rocket hook-callback fatal, and the source/runtime assessment for Shield.

## [2026-10-28] plan | Design visitor standing to replace offence-limit auto-blocking

Pages created: `wiki/plans/visitor-standing-design.md`
Pages updated: `wiki/index.md`, `wiki/log.md`, `wiki/overview.md`, `wiki/glossary.md`
Key notes: Follows the review of `feature/request-policy-engine`, which is not being taken forward. Combines weighted, fading offences, CrowdSec, bot signals and identity into one per-IP standing, with responses graded by request sensitivity. No user-facing shadow or legacy mode.

## [2026-10-28] plan | Revise visitor standing design after owner feedback

Pages created: none
Pages updated: `wiki/plans/visitor-standing-design.md`, `wiki/log.md`
Key notes: CrowdSec listings now mean Hostile. Bot-tracking options removed in favour of built-in weights, four on/off switches and a per-site noise check. Application-password REST requests are judged after WordPress checks the password. Standing is network-wide on multisite.

## [2026-10-28] plan | Drop remaining signal switches from visitor standing design

Pages created: none
Pages updated: `wiki/plans/visitor-standing-design.md`, `wiki/log.md`
Key notes: XML-RPC legitimacy now comes from the existing "Disable XML-RPC" option. Weak signals are capped per visitor, so empty user agents and 404s need no switch. Only link cheese keeps an on/off switch.

## [2026-10-28] plan | Record 404 hotspot viewer as a follow-up idea

Pages created: none
Pages updated: `wiki/plans/visitor-standing-design.md`, `wiki/log.md`
Key notes: Added a post-v1 idea to surface 404 paths with very high counts, so site owners can fix site-caused 404s that penalise real visitors.

## [2026-10-28] plan | Keep feature/request-policy-engine as the working branch

Pages created: none
Pages updated: `wiki/plans/visitor-standing-design.md`, `wiki/log.md`
Key notes: The visitor standing work happens on `feature/request-policy-engine`. The old request policy engine on that branch is removed in Phase 0 and replaced by this design. This supersedes the earlier note that the branch was not being taken forward.

## [2026-10-28] plan | Record evidence strength locations and drop empty user agent signal

Pages created: none
Pages updated: `wiki/plans/visitor-standing-design.md`, `wiki/log.md`
Key notes: Evidence strengths live in `evidence_strength` on offence events and `firewall_evidence_strengths` in the defs. The "Empty User Agents" option has no implementation, so it is removed in Phase 5 rather than built. `bottrack_xmlrpc` is weighted weak until Phase 5 removes it.
