# Shield JavaScript Architecture

## Directory Structure

```
assets/js/
  plugin-main.js              Main plugin admin entry (Shield pages)
  plugin-wpadmin.js           Shared wp-admin entry (loaded on non-Shield admin pages)
  plugin-login2fa.js          Login 2FA entry
  plugin-userprofile.js       User profile MFA entry
  plugin-blockpage.js         Block page entry
  plugin-mainwp_server.js     MainWP server integration entry

  app/                        App bootstraps by context
  components/                 UI and feature components
  services/                   AJAX, events, and app services
  util/                       Shared JS utility modules
```

## Build Commands

```bash
npm install && npm run build    # Production build
npm run dev                     # Development build with watch
```

Output goes to `assets/dist/`.

## February 2026 Incident And Fix

### What Broke

MainWP dashboards started throwing errors such as:

- `No method named "set selected"`
- `No method named "setting"`

These errors came from jQuery plugin name collisions in shared wp-admin pages.

### Root Cause

Between `21.2.2` and `21.2.4`, `assets/js/components/general/SecurityAdmin.js` added:

- `import { Modal } from "bootstrap"`
- `new Modal(...)`

On shared wp-admin pages, this caused Bootstrap JS to register jQuery bridges like:

- `$.fn.dropdown`
- `$.fn.modal`

MainWP uses Semantic UI jQuery plugins with the same names, so Bootstrap and MainWP fought over the same jQuery plugin slots.

### What Was Changed

1. Kept `SecurityAdmin.js` as Bootstrap-modal implementation for `main` (Shield pages) to preserve 21.2.4 UX parity.
2. Added a separate `SecurityAdminWpAdmin.js` for `wpadmin` path with no Bootstrap JS import.
3. Wired `AppWpAdmin` to `SecurityAdminWpAdmin`, while `AppMain` continues to use `SecurityAdmin`.
4. Removed temporary `noConflict()` and temporary overlay CSS workaround.

Result: `shield-wpadmin.bundle.js` no longer carries Bootstrap JS in this path, so the jQuery bridge collision source is removed without changing Shield-page modal behavior.

## Hard Rule: No New jQuery Imports

Future agents must follow this directive for shared wp-admin safety:

1. Do not add Bootstrap JS imports in `assets/js/plugin-wpadmin.js` dependency path.
2. Prefer vanilla DOM APIs (`querySelector`, `addEventListener`, `classList`, `fetch`, `FormData`) for `wpadmin` components.
3. If a feature needs Bootstrap JS behavior, keep it in the `main` path and split the implementation by bundle when necessary.

Legacy jQuery usage exists in older code. Treat it as migration debt, not a pattern to extend.

## Shared wp-admin Safety Rules

For any code that ships in the `wpadmin` bundle:

1. Assume third-party plugins are present and may use jQuery plugins with common names.
2. Do not register global plugin names on `$.fn`.
3. Avoid JS libraries that auto-attach jQuery interfaces on load.
4. Keep behavior isolated to Shield DOM nodes and selectors.

## Review Checklist For JS Changes

Before merging any JS change:

1. Confirm which bundle the file lands in (`main` vs `wpadmin`).
2. If it affects `wpadmin`, verify no jQuery plugin bridges are introduced.
3. Smoke-test with MainWP active on a standard WordPress dashboard screen.
4. Run `npm run build` and verify `assets/dist/shield-wpadmin.bundle.js` output.
