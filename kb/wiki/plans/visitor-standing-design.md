---
title: Visitor Standing and Proportionate Response - Design and Spec
type: topic
created: 2026-10-28
updated: 2026-10-28
sources: []
tags: [ip-blocking, offences, crowdsec, bot-signals, silentcaptcha, request-policy, design]
category: plans
---

# Visitor Standing and Proportionate Response - Design and Spec

Replaces Shield's offence-limit auto-block path with one per-IP judgement (visitor standing) and a response matched to both that standing and what the request could do.

Status: design agreed in principle, not started. Work happens on `feature/request-policy-engine`. The request policy engine already on that branch (commits `844865d16`..`70c8f0eff`) was reviewed on 2026-10-28 and is replaced by this design. Two of its refactors are kept (see "Phase 0").

## 1. Why

Shield's IP protection today is binary:

- Every offence adds to a per-IP count. At the Offence Limit (`transgression_limit`, default 10) the IP is fully blocked.
- The count does not fade. It only resets when the IP has been idle for the Auto Block Expiration (`auto_expire`, default one hour), because the auto-block record is deleted once `last_access_at` is that old. A blocked IP that keeps visiting keeps its block alive.
- Offences are equal unless the site owner sets a bot-tracking option to "double". An exploit payload and a stray 404 can count the same.
- A CrowdSec listing is a blanket block (`cs_block`, default "block with unblock").
- Self-unblock for Shield auto-blocks (`user_auto_recover`) is off by default.

This fails in both directions. Real visitors on shared, mobile or office IPs get locked out with no middle ground. Attackers who rotate IPs rarely reach a per-IP limit of 10.

The goal is a proportionate response: the friction a request meets depends on how sure we are the visitor is hostile, and how much harm this request could do.

## 2. Principles

1. Two questions decide every request: the visitor's standing (risk) and the request's sensitivity (what it could do).
2. Responses come in steps: allow -> challenge -> throttle -> block. A challenge is the answer when we are unsure. Hard blocks are for high confidence.
3. Detectors supply weighted evidence, not verdicts. One standing per IP, worked out from all of it.
4. Evidence is weighted by how reliable it is, and it fades.
5. Proof of humanity lowers friction. It never overrides the site owner's manual decisions.
6. Every decision can be explained to the admin and reversed.
7. One system. No user-facing shadow or legacy mode. A development-only shadow (constant, no UI) is used for validation before release.
8. Reuse existing settings and data. Add no new settings unless validation shows a need.

## 3. Inputs

| Input | Today | Role in this design |
|---|---|---|
| Offence events (`offense: true` in `plugin-spec/46_events.json`) | Each adds to the IP's offence count | Hostility evidence, weighted (section 4) |
| Bot-tracking options (`track_404`, `track_loginfailed`, `track_logininvalid`, `track_xmlrpc`, `track_fakewebcrawler`, `track_linkcheese`, `track_invalidscript`, `track_useragent`) | disabled / log / single / double / block | Removed. Every signal gets a built-in weight. Only link cheese keeps an on/off switch (section 4) |
| Bot signals and bot score (`bot_signals` table, scoring logic from ShieldNET) | Recency-weighted score per IP. silentCAPTCHA treats scores below `antibot_minimum` (45) as bots. Scores above `antibot_high_reputation_minimum` (250) exempt the IP from auto-blocks | The humanity side: proof of humanity at challenge time and the high-reputation cap (section 5) |
| Manual blocks and the bypass list (IP rules) | Absolute | Unchanged and always win |
| Auto-block record (IP rules, type auto-block) | Created on the first offence, holds the offence count, set to blocked at the limit | Becomes the stored standing record for the IP (section 9) |
| CrowdSec decisions (IP rules, type CrowdSec) | Blanket block, optional self-unblock | A listing makes the visitor Hostile (section 5) |
| Identity: session, capabilities, Security Admin, trusted services | Various bypasses | Trusted standing or proof of humanity (section 5) |
| IP context (ASN, country, proxy flag in `ip_meta`) | Display | Not evidence in v1 |

The bot score measures recency of signals, not frequency: ten failed logins and one failed login leave the same `btloginfail_at` timestamp. That is why the hostility side is built on weighted offences, and the bot score is used for the humanity side.

## 4. Evidence and weights

Hostility evidence is measured in offence units, the same unit as the Offence Limit, so that setting keeps its meaning.

Every offence event gets a built-in weight, declared as `evidence_strength` on the event definition in `plugin-spec/46_events.json`, so the list cannot drift out of step with the events. Firewall blocks are weighted by firewall category, declared as `firewall_evidence_strengths` in `plugin-spec/37_defs.json` beside the firewall patterns. The event's own strength applies to firewall blocks with no category, such as custom firewall rules. The five-level bot-tracking options (disabled / log / single / double / block) are removed. Site owners no longer decide how much each signal counts. Shield does.

| Strength | Weight | Events (starting proposal) |
|---|---|---|
| Strong | 3 | firewall: PHP code, directory traversal; fake web crawler; link cheese (trap link) |
| Normal | 1 | failed login; invalid username; invalid script load; firewall: SQL queries; 2FA failures; application password failures; Security Admin PIN failure (`key_fail`); attempted Shield deactivation; `user_form_bot_fail`; comment spam blocked; comment marked as spam; request rate limit exceeded; XML-RPC blocked |
| Weak | 0.5 | 404; firewall: field truncation, aggressive; author probing; form submission cooldown (`cooldown_fail`); invalid registration email |

Signal switches and limits:

- Only link cheese keeps an on/off switch, because it changes the site's output (a hidden trap link and a `robots.txt` entry). Every other signal is always on.
- XML-RPC needs no signal of its own. The existing "Disable XML-RPC" option (`disable_xmlrpc`) already says whether XML-RPC is legitimate on the site. When it is on, XML-RPC requests are blocked and count as "XML-RPC blocked" (normal weight). When it is off, XML-RPC access is not evidence. Failed logins over XML-RPC count as failed logins either way, because Shield's failed-login tracking hooks WordPress's `authenticate` filter, which XML-RPC logins also use. The separate XML-RPC bot-tracking signal is removed.
- Each weak signal counts at most 2 offence units per visitor within its fading window (4 occurrences). Repeating the same weak signal is not stronger evidence: a monitor or a visitor clicking around a site with broken links, can never become Doubtful from that alone (Doubtful starts at 4). Two different weak signals together can reach Doubtful. That only adds challenges on forms and similar requests, never on ordinary pages.
- There is no empty-user-agent signal. The "Empty User Agents" option (`track_useragent`) has no implementation behind it today (no tracker, rule or event), so it is removed rather than built.
- No signal makes a visitor Hostile on its own. The old "block" level is gone, because it breaks the guardrail that no single signal decides. Strong signals still reach Hostile quickly: four at the default Offence Limit.

Working out whether a signal is meaningful:

- Per visitor: proof of humanity (section 7) cancels that visitor's weak evidence. A real visitor who hits a few 404s, then passes silentCAPTCHA, carries no penalty for them. A successful login does not cancel failed-login evidence, because success after many failures is a compromise warning sign. Real users who mistype a few times are challenged invisibly, not blocked.
- Per site: Shield tracks, for each weak signal, how many of the visitors who triggered it later showed proof of humanity. If most did (starting values: over half, from at least 20 visitors in 7 days), the signal is noise on this site. Its weight is automatically reduced to zero, and IP analysis and the settings page tell the admin. It returns to normal if the pattern stops. This only ever lowers weights, and only for weak signals, so an attacker cannot use it to raise anyone's penalty, and normal and strong signals cannot be worn down.

Rules:

- Weights are starting values, to be confirmed during validation (section 13). Because they are declared centrally, they can later be tuned for all sites from ShieldNET in the same way as the bot scoring logic.
- A unit test fails if any event with `offense: true` has no weight, or if a weighted event is not an offence.
- Evidence fades. Weak and normal evidence halves every 24 hours. Strong evidence halves every 7 days. (Starting values.)
- The firewall still blocks the matching request itself, as today. Weights only decide what happens to the visitor's other requests.

## 5. Visitor standing

Each visitor has one standing. IPv4 is tracked per address. IPv6 is tracked per /64, because a /64 normally belongs to one subscriber or device and rotating within it is trivial. On multisite, standing is shared across the whole network, matching Shield's network-wide settings.

| Standing | Reached when |
|---|---|
| Trusted | On the bypass list; a trusted service; a Security Admin session; or a logged-in user who can edit posts (`edit_posts` or above) |
| Normal | Default |
| Doubtful | Faded offence score reaches a third of the Offence Limit (rounded up: 4 at the default of 10); or a CrowdSec-listed visitor who has passed a challenge |
| Hostile | Faded offence score reaches the Offence Limit; or listed by CrowdSec (unless `cs_block` is disabled); or a CrowdSec-listed visitor who passed a challenge and whose score has since reached half the Offence Limit |
| Blocked | Manual block only |

CrowdSec is treated as Hostile because its listings have proved reliable: there has been no negative feedback about them in years.

Caps and overrides:

- Manual block -> always Blocked. Bypass list -> always Trusted. Nothing automatic changes either.
- High reputation (bot score above `antibot_high_reputation_minimum`) caps offence-based standing at Doubtful. It does not affect a CrowdSec listing, as today. Today high reputation hides auto-blocks entirely. Under this design such visitors can be challenged, which they will pass, but are never hard-blocked because of offences.
- Other logged-in users (subscribers, customers) are not Trusted. Their session counts as proof of humanity, so they are not challenged, but evidence still accrues and Hostile still blocks them. This covers spam accounts on sites with open registration.
- Requests authenticated with an application password follow the same rules as a logged-in user for that account, once WordPress has checked the password (section 7). With a correct password, a visitor only becomes Doubtful by triggering evidence repeatedly.
- Hostile holds for at least the Auto Block Expiration after the IP's last evidence, even if the score has faded below the threshold. Visiting while Hostile does not extend the hold. Only new evidence does.

Leaving a standing:

- Evidence fades, so standing steps down on its own.
- Passing a challenge drops Hostile to Doubtful and reduces the score to just below the Doubtful threshold. Failing one is normal-weight evidence.
- A CrowdSec-listed visitor who passes a challenge drops to Doubtful, as today's CrowdSec self-unblock does. The listing itself stays, so they reach Hostile again at half the Offence Limit.
- An admin reset clears the evidence and returns the IP to Normal. An admin unblock does the same.

## 6. Request sensitivity

Requests are classified by what they could do, not by HTTP method.

| Sensitivity | Requests |
|---|---|
| Open | Viewing content with no input: front-end pages and posts without query parameters (other than known harmless ones such as pagination and UTM tags), feeds, sitemaps, oEmbed, static assets that reach PHP |
| Guarded | Anything that takes input or runs code for anonymous visitors: search; any front-end request with unrecognised query parameters; any POST not listed as Critical; comments; registration; lost password; contact and other forms; unauthenticated `admin-ajax.php` and `admin-post.php` (GET or POST); unauthenticated REST requests (GET or POST); WooCommerce cart, checkout, `wc-ajax` and Store API; GraphQL |
| Critical | Authentication of any kind: `wp-login.php` login, 2FA, XML-RPC, application-password REST, WooCommerce account login; Shield actions; authenticated admin requests |

Rules:

- Anything not recognised is Guarded.
- Plugins and site owners can classify their own routes and AJAX actions through a filter. Shield ships classifications for WordPress core and WooCommerce.
- Classification runs only when the visitor's standing is Doubtful or Hostile. Normal and Trusted visitors are allowed everywhere, so their requests never need classifying (section 10).

## 7. Decisions and responses

| Standing | Open | Guarded | Critical |
|---|---|---|---|
| Trusted | allow | allow | allow |
| Normal | allow | allow | allow (existing login protections still apply) |
| Doubtful | allow | challenge | challenge, and throttle repeat attempts |
| Hostile | challenge if self-unblock is available, otherwise block | block | block |
| Blocked | block | block | block |

A challenge means "show proof of humanity", using Shield's existing silentCAPTCHA and AltCha:

- For a page request (top-level GET for HTML): an interstitial page runs silentCAPTCHA/AltCha and, for Hostile visitors, the self-unblock option. On a pass the visitor continues to the page they asked for.
- For any other request (POST, AJAX, REST, XML-RPC): no page can be shown, so the request needs fresh proof of humanity already on record. That means a notbot or AltCha signal within the silentCAPTCHA refresh window and a bot score at or above `antibot_minimum`, or a logged-in session. Without it the request is refused with a "verification required" response (JSON for AJAX and REST). Real visitors normally have the proof already, because silentCAPTCHA runs on the page they came from.
- Requests carrying credentials (application passwords on REST): WordPress only checks an application password for a REST request once it has routed the request, which is after Shield's gate runs. So for a Doubtful visitor, the decision on a REST request carrying credentials waits until WordPress has checked them. A correct password means the logged-in user rules apply. A wrong one is evidence (the existing application password failure events) and the request is refused. XML-RPC is recognised from the start, so it can be decided straight away. A Hostile visitor is blocked before the password is checked, the same as a login attempt from a Hostile visitor.
- Shield's own challenge and self-unblock endpoints (`capture_not_bot`, AltCha, auto-unblock) are never gated. The reviewed branch broke this.
- Throttle: a Doubtful visitor gets a limited number of Critical attempts per window (starting value: 5 per 15 minutes). Going over the limit is normal-weight evidence.
- Block: the existing block pages. Visitors who are Hostile automatically get the self-unblock routes the owner has enabled. Manually Blocked visitors get none, as today.

## 8. How current settings carry over

| Setting | New meaning |
|---|---|
| Offence Limit (`transgression_limit`) | Faded score at which a visitor becomes Hostile. A third of it makes them Doubtful |
| Auto Block Expiration (`auto_expire`) | Minimum time a visitor stays Hostile after their last evidence |
| User Auto Unblock (`user_auto_recover`) | Which self-unblock routes Hostile visitors are offered, and so whether Hostile on Open means challenge or block |
| CrowdSec IP Blocking (`cs_block`) | Listing means Hostile. "block with unblock" -> CrowdSec self-unblock is offered, so Hostile on Open means a challenge. "block" -> no self-unblock, so the visitor is blocked everywhere. "disabled" -> listing ignored |
| Bot-tracking options (`track_*`) | Removed, except an on/off switch for link cheese (off on upgrade if it was "disabled", otherwise on). Built-in weights replace single/double/block. The XML-RPC signal is replaced by the existing "Disable XML-RPC" option (section 4) |
| silentCAPTCHA scores (`antibot_minimum`, `antibot_high_reputation_minimum`) | Proof-of-humanity threshold and the high-reputation cap |
| Manual blocks, bypass list | Unchanged |

On upgrade, existing auto-block records seed the standing. Blocked IPs become Hostile, holding for the remaining Auto Block Expiration. Offence counts carry over 1:1 as the starting score.

Behaviour change to call out in the release notes: several signals default to "log" today (404, invalid usernames, fake web crawler, invalid script load), which adds no offence. After upgrade they count at their built-in weight, and owners can no longer turn them off. Because uncertain cases get a challenge rather than a block, weak signals are capped, and the per-site noise check lowers noisy signals, this should add little friction for real visitors. Anonymous XML-RPC access, tracked as "log" today, stops counting when XML-RPC is allowed. Validation (section 13) must confirm that.

Option labels and help text are rewritten to describe the new behaviour. There is no switch back to the old behaviour.

## 9. Storage

- Standing lives on the existing per-IP auto-block record, extended with the faded score, when the score was last updated, and the hold time. No new table. The lookup is the IP rules lookup Shield already does on every request.
- Standing is worked out when evidence arrives, not when it is read. The record stores the standing plus the time it next drops, so reading needs no calculation and fading needs no cron job.
- Evidence is written once per request, at shutdown, folded into the offence write that already happens. The update is a single statement that adds to the stored score directly, so parallel requests from one IP do not lose evidence.
- Weak evidence updates from one IP are capped at one every few seconds, to bound writes during a flood.

## 10. Performance

Budget:

- Normal visitors: no extra database queries beyond today's IP rules lookup.
- Doubtful and Hostile visitors: at most one extra indexed read (for proof of humanity, when a challenge applies).
- Writes: only when there is evidence, one per request.

Known limits:

- Full-page caches (WP Rocket, LiteSpeed, Cloudflare APO, Varnish) serve cached pages without running PHP, so no check runs on those requests. Today's IP blocks have the same limit. In practice this only weakens "Hostile on Open". Guarded and Critical requests are rarely cached. Challenge and block responses must never be cached.
- The IP rules "no rules" cache holds 30 IPs for 60 seconds. On busy sites without a persistent object cache it churns, so each request does one indexed database read. That is no worse than today.
- The decision gate runs where Shield's rules run (`after_setup_theme`). The early-loading work may move it earlier later. That is out of scope here.

## 11. Admin visibility and control

- IP analysis shows the standing, faded score, the main evidence behind it, CrowdSec status, proof-of-humanity freshness and any hold time.
- Each IP has reset, trust (bypass) and block controls that take effect immediately.
- Activity log events record standing changes, challenges issued, passed and failed, throttles, and blocks, each with the reasons. Allowed requests are not logged.
- Reports count challenges and blocks separately, so owners can see friction as well as blocks.

## 12. Guardrails and non-goals

Guardrails:

- Manual decisions always win.
- Users who can edit posts, and Security Admins, are never challenged or automatically blocked.
- If we are unsure, the answer is a challenge, not a block.
- No single weak signal can make a visitor Hostile.

Out of scope for v1:

- Pooling evidence across IPv4 subnets or ASNs.
- Sending standing back to ShieldNET.
- Using IP context (country, ASN, proxy) as evidence.
- Early loading (separate project).
- A user-facing shadow or legacy mode.

## 13. Validation and release bar

- Development-only shadow: a constant (no UI, no option) that works out and logs decisions without enforcing them. Used on sites we control for at least two weeks, comparing against the current behaviour.
- Traffic replay: run stored traffic-log records through the classifier and standing model offline. The traffic log stores request type, method, path, response code, user and offence flag, so it can check classification and standing, but not firewall payloads. How much traffic it holds depends on each site's logging settings.
- Scenario tests for realistic WordPress requests: unauthenticated AJAX and REST GETs, WooCommerce checkout and Store API, registration and lost password, XML-RPC, application passwords, and the silentCAPTCHA/AltCha endpoints from challenged visitors.
- Concurrency test: parallel requests from one IP must not lose evidence.
- Performance check: query count and time per request type (Normal visitor on Open, Doubtful visitor on Guarded, a 404 flood, a cached page), with and without an object cache, against the current release.

Release only when:

- No known human is blocked where they should only have been challenged.
- No known attack that the current version stops gets through.
- Normal-visitor requests show no extra queries.

## 14. Phases

Each phase is independently completable and verifiable.

0. Clear the ground. On `feature/request-policy-engine`, keep the request-type resolver (with its classification tests) and the per-rule condition-meta snapshot. Remove the rest of the old request policy engine: its components, table, options, events, rule gate and `BaseAction` hook, and their tests. Shield's behaviour then matches the current release again.
1. Evidence spec. Add `evidence_strength` to every offence event and `firewall_evidence_strengths` for the firewall categories, with a completeness test. No behaviour change. The XML-RPC bot-tracking event (`bottrack_xmlrpc`) is weighted weak until Phase 5 removes it.
2. Standing, view only. Store and work out standing on the auto-block record, seed it from existing records, and show it in IP analysis. Add the development-only shadow constant. Enforcement unchanged.
3. Request sensitivity. Classifier, filter, core and WooCommerce classifications, scenario tests.
4. Challenges. Interstitial page, proof-of-humanity check for non-page requests, deferred decision for REST requests carrying credentials, exemptions for Shield's own endpoints, throttle.
5. Decision gate. One gate per request applying the table in section 7. It replaces the Shield and CrowdSec IP block rules and the offence-limit block. Remove the bot-tracking options, keeping only the link cheese switch. Remove the XML-RPC bot-tracking signal (`bottrack_xmlrpc` and its rule) and the unimplemented `track_useragent` option. Migrate settings, rewrite option labels and help text. Add the activity log events.
6. Noise check. Per-visitor cancelling of weak evidence by proof of humanity, the per-site noise check for weak signals, and how both are shown to the admin.
7. Validate and release, against section 13.

## 15. Decisions taken

| # | Decision | Why |
|---|---|---|
| 1 | A CrowdSec listing means Hostile. `cs_block` decides only whether self-unblock is offered, or turns CrowdSec off | Listings have proved reliable for years |
| 2 | Hostile on Open pages gets a challenge only when a self-unblock route is enabled, otherwise a block | Follows the owner's existing self-unblock setting |
| 3 | IPv6 is tracked per /64 from v1. No wider pooling | Closes trivial rotation without pooling unrelated visitors |
| 4 | No feedback to ShieldNET in v1 | Keeps scope down. Can follow once the model is proven |
| 5 | No user-facing shadow mode | One system. Shadow is a development tool only |
| 6 | The bot score is the humanity side, not the hostility counter | It records recency, not frequency |
| 7 | Bot-tracking options are removed. Shield sets every signal's weight. Only link cheese keeps an on/off switch. Weak signals are capped per visitor, and a per-site noise check lowers weak signals that mostly hit real visitors | Owners should not have to tune weights. Caps and the noise check handle sites that trigger signals legitimately. Link cheese changes the site's output, so owners must be able to turn it off |
| 11 | XML-RPC legitimacy comes from the existing "Disable XML-RPC" option, not a separate signal | The site owner has already said whether XML-RPC is in use |
| 8 | No signal makes a visitor Hostile on its own | A single signal should never decide |
| 9 | Application-password requests are judged after WordPress checks the password. A correct password gets the logged-in user rules | A known account with the right password is not a doubtful visitor |
| 10 | On multisite, standing is network-wide | Shield has no per-site settings |

## 16. Open questions

1. Should visitor self-unblock (`user_auto_recover`) be enabled by default with this release? It is off today, which means Hostile visitors on Open pages are blocked, not challenged.
2. Integrations that log in with application passwords from cloud-hosting addresses that end up on the CrowdSec list are blocked, the same as a login attempt from a Hostile visitor. The bypass list is the fix. Is that enough, or should well-known management services be added to the trusted services?

## 17. Follow-up ideas (after v1)

### 404 hotspot viewer

404s are the clearest example of the current system's weakness. A broken link, a missing theme asset or a stale URL after a migration makes real visitors collect offences through no fault of their own. The weak-signal cap (section 4) stops this getting anyone blocked. It does not fix the cause.

Idea: a view that surfaces only 404 paths with very high counts, especially across many different visitors. These point to something on the site causing the 404s, not to probing. The admin can then fix the link or asset, or redirect it, which removes the penalty for real visitors at the source.

Notes:

- Not a list of all 404s. Only paths far above the site's normal level are shown.
- Data already exists: the traffic log stores path and response code, and the 404 bot-tracking event records the path. How much history is available depends on the site's logging settings.
- Possible extension: a path hit by many visitors who later show proof of humanity could stop counting as 404 evidence automatically. This is a path-level version of the per-site noise check in section 4.

## Related Pages

- [[index]] - master catalog of wiki pages
- [[glossary]] - visitor standing, request sensitivity and proof of humanity terms
