---
title: WordPress 7.1 Plugin Compatibility Research
type: analysis
created: 2026-08-21
updated: 2026-08-21
sources: []
tags: [wordpress, wordpress-7-1, plugin-compatibility, research]
category: analyses
---

# WordPress 7.1 Plugin Compatibility Research

WordPress 7.1.0 has several documented integration changes, but the primary sources reviewed do not identify a confirmed, still-unfixed 7.1.0-wide plugin failure.

## Confirmed Compatibility Risks

| Change | Who can be affected | Why it trips plugins |
|---|---|---|
| Post editor is always iframed | Block plugins, editor extensions, and legacy blocks | Editor-canvas code using global `document` or `window` now addresses the parent admin page rather than the canvas. The editor is iframed regardless of block API version or legacy meta boxes. Use a canvas element's `ownerDocument` / `defaultView` and attach listeners to canvas elements. [Core dev note](https://make.wordpress.org/core/2026/08/03/iframed-editor-changes-in-wordpress-7-1/) |
| Client-side media processing is enabled by default in supported Chromium browsers | Media, watermarking, CDN, attachment-processing, CSP, and editor-embed plugins | Image processing moves into a browser worker. `wp_image_editors`, `image_memory_limit`, and `image_make_intermediate_size` do not run on that path; `wp_generate_attachment_metadata` remains compatible but is called on `create` then `update`. The editor gains document isolation: external scripts become anonymous-CORS requests, browser image fetches can fail CORS, and restrictive CSP needs `worker-src 'self' blob:`. [Core dev note](https://make.wordpress.org/core/2026/07/22/client-side-media-processing-in-wordpress-7-1/) |
| Post list-table markup changed | Plugins adding CSS/JS to post list screens | `th.check-column` became `td.check-column`; title/row-actions became descendants of the row header `th`, not `td`; collapsed cells use flex. Existing selectors and event bindings can silently stop matching. [Core dev note](https://make.wordpress.org/core/2026/08/03/post-list-tables-row-headers-changed/) |
| jQuery UI 1.14.2 | Plugins using bundled jQuery UI internals | Core moved from 1.13.3 and removed `$.fn._form`, `$.ui.ie`, `$.ui.safeActiveElement`, and `$.ui.safeBlur`. Core does not use them, but a plugin that does must replace them. [Core dev note](https://make.wordpress.org/core/2026/07/29/jquery-ui-updated-to-1-14-2-in-wordpress-7-1/) |
| Media Library grid defaults to infinite scroll | Plugins extending the Media Library or assuming "Load more" pagination | The existing filter signature is unchanged, but its default is now `true` in both the grid and media modal. Extensions relying on the old default need to set the filter explicitly. [Core dev note](https://make.wordpress.org/core/2026/07/23/media-library-infinite-scrolling-is-now-enabled-by-default-with-a-per-user-opt-out/) |
| Persistent admin toolbar in Site Editor | Plugins adding admin-bar nodes | The Site Editor now has the persistent toolbar, so toolbar nodes must be checked in that screen and during client-side navigation. [Core dev note](https://make.wordpress.org/core/2026/07/13/consistent-navigation-in-wordpress-7-1-with-persistent-toolbar/) |
| `notify_post_author` behavior | Comment-notification plugins | A callback returning `true` now sends notifications for unapproved, spam, or trashed comments; the input is now strictly boolean, and invalid IDs no longer invoke the filter. [Core dev note](https://make.wordpress.org/core/2026/08/05/the-notify_post_author-filter-now-has-the-final-say-on-post-author-notifications/) |

## Vendor-Confirmed Incident

WP Rocket reported a fatal error while testing WordPress 7.1 alpha on PHP 8: it passed an integer WordPress hook-callback identifier to `substr()`, which requires a string. The fault appeared in configurations where another plugin registered the relevant callbacks, and WP Rocket shipped a specific WordPress 7.1 compatibility fix in version 3.23.2.2. This is a concrete example of a plugin depending on the representation of a Core hook callback key, rather than a general WordPress 7.1 failure. [WP Rocket issue](https://github.com/wp-media/wp-rocket/issues/8596) and [WP Rocket changelog](https://wp-rocket.me/changelog/).

## Confirmed Pre-release Regression That Is Not A 7.1.0 Risk

Gutenberg 23.6 briefly had a recursion and memory-exhaustion regression when a plugin rendered blocks from a `the_posts` callback. The upstream fix was merged and explicitly backported to the WordPress 7.1 branch before release, so it should not be classified as an outstanding 7.1.0 conflict. [Regression report](https://github.com/WordPress/gutenberg/issues/80770) and [merged/backported fix](https://github.com/WordPress/gutenberg/pull/80771).

## Assessment Boundaries

- These are Core-documented behavior changes, not a list of third-party support anecdotes.
- The Field Guide records that React 19 and removal of the Classic block were deferred, so neither is a WordPress 7.1.0 compatibility cause. [WordPress 7.1 Field Guide](https://make.wordpress.org/core/2026/08/05/wordpress-7-1-field-guide/)

## Shield Assessment

Shield does not register block-editor assets or use WordPress editor packages, Media Library extension APIs, image-processing callbacks, `notify_post_author`, jQuery UI internals, or the internal `$wp_filter` / `WP_Hook->callbacks` representation. Its only matching post-table CSS targets Shield's own `.scan-table`, rather than Core post-list markup. Its admin-bar integration uses the supported `admin_bar_menu` action and has small CSS rules for Shield's own menu/counter; the persistent Site Editor toolbar is therefore a presentation check, not an identified functional incompatibility.

The latest-runtime integration lane also passed against WordPress 7.1.0 on 2026-08-21: `php bin/shield test:source --skip-unit-tests --show-docker-output` completed 1,427 tests with 14,250 assertions and 25 expected skips. This is strong runtime evidence for Shield's PHP and WordPress integration surface, but it does not substitute for a visual browser check if Shield later adds an editor-canvas or Site Editor feature.

## Related Pages

- [[index]] - master catalog of wiki pages
