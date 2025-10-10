## Lightweight Action Definitions – Session Notes

### Context
- Objective: reduce explosion of `BaseAction` subclasses by introducing a callable-based action pipeline that keeps existing access and policy checks.
- Proof-of-concept implemented on branch `feature/lightweight-action-registry`.

### Components Added
1. `Simple/Definition` – value object describing slug, handler callable, defaults, required keys, and policy hints.
2. `Simple/Registry` – in-memory store of `Definition` objects (`register()`, `get()`, `has()`, `all()`).
3. `Simple/Dispatcher` – enforces current policy checks (nonce, capability, security admin, IP) before invoking the handler; merges defaults and populates `ActionResponse`.
4. `Simple/DefinitionsRegistrar` – central place to register callable actions (currently registers `mark_tour_finished` without a class).
5. `ActionRoutingController` – now lazily exposes `simpleRegistry()` and `simpleDispatcher()` accessors and runs the registrar when the registry is first requested.

### Examples
- `Simple/Examples/MarkTourFinishedExample` demonstrates:
  - Registering the existing `PluginMarkTourFinished` class via its `simpleDefinition()` helper.
  - Registering a pure-closure definition (`mark_tour_finished_closure_demo`) to show a class-free variant.
  - Dispatching both versions through the dispatcher.
- `Simple/Examples/DismissAdminNoticeExample` mirrors the same pattern for `DismissAdminNotice` with closure and class-backed registrations.
- `Simple/Examples/ConvertedMarkTourFinished` shows a **full class replacement** using only a `Definition` (no `BaseAction` subclass); real wiring lives in `Simple/DefinitionsRegistrar`.
- `Constants::ACTIONS` no longer lists `PluginMarkTourFinished::class`; the slug is resolved purely through the registry.

### Action Class Updates
- `PluginMarkTourFinished` and `DismissAdminNotice` now expose `simpleDefinition()` that reuses their internal logic, enabling dual registration without behaviour changes.
- Minimal refactor pattern: move body of `exec()` into a shared handler method and call it from both `exec()` and the closure inside `simpleDefinition()`.

### Migration Outline
1. Centralise registrations inside `Simple/DefinitionsRegistrar::registerDefaults()` (or split by feature). Replace entries in `Constants::ACTIONS` with callable definitions as they’re migrated.
2. For each action still backed by a class, introduce a shared `handle()` (or equivalent) method and add `simpleDefinition()` so the registrar can register it alongside the class.
3. Once confidence is high, replace specific classes with `Definition` closures and remove the class + constant entry, reducing boilerplate gradually.
4. Future (PHP 8+): consider attributes or docblock markers to auto-register definitions; reflection cost is negligible if cached at bootstrap.

### Key Considerations
- Policies default to the same behaviour as `BaseAction`: nonce check uses AJAX heuristic, capability falls back to plugin base permissions, security admin requirement mirrors `manage_options` default.
- Dispatcher accepts an optional `ActionResponse`; handlers may return an array (auto assigned to `action_response_data`) or a full `ActionResponse` instance.
- Registry is currently process-local; persistent wiring should happen during controller bootstrap to avoid re-registration per request.

### Next Steps
1. Decide the integration point to populate the registry during plugin bootstrap.
2. Identify a small set of low-risk actions to convert entirely to closure definitions for a first production test.
3. When PHP 8+ is available, evaluate attributes to simplify registration metadata.
4. Optionally add an interface/trait for classes exposing `simpleDefinition()` to automate discovery.

These notes capture the current PoC structure so future sessions can continue without re-deriving the approach.
