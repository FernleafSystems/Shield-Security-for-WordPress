---
name: shield-changelog-update
description: Update Shield Security's repo-local cl.json changelog for major and patch releases. Use when Codex needs to inspect commits since the release anchor, choose the correct major or hotfix patch target, translate code/tooling/internal work into concise user-facing release notes for WordPress admins, agencies, and MSPs, or review changelog tone and structure.
---

# Shield Changelog Update

## Workflow

1. Read `cl.json`, then identify the target release:
   - On `develop`, update the upcoming major release, usually the latest top-level key such as `"22.0"`.
   - On a hotfix branch, parse the version from the branch name. `hotfix/22.1.7` targets major key `"22.1"` and patch `"version": "7"`.
   - For hotfixes, update the existing patch entry if present; otherwise create it under the matching major release.
2. Use the latest commit touching `cl.json` as the changelog anchor: `git log -1 -- cl.json`, then review `anchor..HEAD`.
   - If the user gives a different explicit release anchor, use it and state that assumption.
3. Review commits/diffs since the anchor plus relevant current workspace changes. Add only changes with user-facing value.
4. Translate internal work into admin-visible benefit. Edit only the target release or patch block.
5. Validate JSON, reread the changed block, and remove wording that sounds like a commit message.

## `cl.json` Shape

Top-level keys are major releases: `"22.0"`, `"22.1"`, etc. A major release has:

- `version`: major string, e.g. `"22.0"`
- `released_at`: Unix timestamp, rounded to the nearest 1000 seconds when setting/changing it
- `hrefs.release`, `hrefs.upgrade`
- `title`
- `description`: 1-3 high-level strings
- `items`: major-release notes
- `patches`: patch-release notes

Patch entries live under the matching major release:

```json
{
  "version": "7",
  "released_at": 1776686000,
  "items": []
}
```

Timestamp rounding examples: `123456499 -> 123456000`, `123456500 -> 123457000`, `123456999 -> 123457000`, `1776686400 -> 1776686000`. Do not churn existing timestamps just to round them.

## Audience And Tone

Audience: WordPress admins, agencies, MSPs, and security-conscious owners. They want to know what Shield does better for their site, not how the code changed.

Keep prose:

- Short, concrete, benefit-led.
- Confident, not hype-heavy.
- Admin-readable, with technical terms only when they clarify value.
- Grouped by outcome where possible instead of one entry per commit.

Avoid:

- Class names, CI lanes, PHPStan, package names, test fixtures, dependency bumps, internal refactor names.
- Empty phrases such as "misc fixes", "various improvements", "code cleanup".
- Developer wording such as "normalized producer contracts" or "removed brittle assertions".

## Translate Internal Work

If internal work improves release quality, deployment safety, upgrade reliability, performance, or admin trust, expose that benefit. If it has no useful customer-facing benefit, omit it.

Bad:

- `Fix CI package and test regressions`
- `Normalize PHPStan contracts`
- `Bump fast-uri from 3.0.6 to 3.1.2`

Good:

- `Release Stability Improved` - `Automated tests and checks catch more issues before release.`
- `Upgrade Reliability Improved` - `Upgrade handling is more reliable, reducing the chance of disruption after updating Shield.`

Better:

- `Release Reliability Improved` - `Deployment checks now catch more packaging and upgrade issues before release, helping Shield updates arrive more reliably.`

## Entry Examples

Bad:

```json
{
  "type": "improved",
  "title": "Refactor scan queue reconciliation",
  "description": [
    "Centralized queue cleanup and completed parent scans after each processed queue item."
  ]
}
```

Good:

```json
{
  "type": "improved",
  "title": "Scan Handling",
  "description": [
    "Shield is better at recognising when scan queues have finished, reducing stuck or incomplete scan states."
  ]
}
```

Better:

```json
{
  "type": "improved",
  "title": "Scan Reliability Improved",
  "description": [
    "Scans recover more cleanly when queue work finishes or stalls, reducing the chance of stuck scan results on busy sites."
  ]
}
```

Bad:

```json
{
  "type": "improved",
  "title": "Update JS dependencies",
  "description": [
    "Updated package-lock and removed unused npm modules."
  ]
}
```

Good:

```json
{
  "type": "improved",
  "title": "Admin Performance and Reliability",
  "description": [
    "Shield admin screens load with fewer unnecessary assets, keeping the plugin leaner while you work."
  ]
}
```

Better:

```json
{
  "type": "improved",
  "title": "Leaner Admin Screens",
  "description": [
    "Shield loads fewer unused admin assets, helping admin screens stay lighter and more reliable."
  ]
}
```

## Acceptance Criteria

Complete only when:

- The correct target is chosen from branch context: `develop` means major release; hotfix branch version determines the patch entry.
- Commits since the `cl.json` changelog anchor are reviewed, unless the user gave a different explicit release anchor.
- Every added entry explains admin value, not implementation detail.
- Internal/tooling work is either translated into release/deployment/upgrade benefit or omitted.
- `cl.json` parses as JSON.
- The changed release or patch block has been reread start to finish.
- No exact implementation-only terms remain unless they are established Shield product vocabulary.
