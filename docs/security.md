# Security Boundaries And Deferred Hardening

This document records security-boundary assumptions that are currently accepted, but that must be revisited if Shield's access model changes later.

## Current Accepted Boundary

Shield's shared request-meta lookup is currently treated as broad within the existing WordPress admin / Security Admin boundary.

The following entrypoints currently rely on that model:

- `src/ActionRouter/Actions/HandlesRequestMetaTableAction.php`
- `src/DBs/ReqLogs/GetRequestMeta.php`
- `src/ActionRouter/Actions/ActivityLogTableAction.php`
- `src/ActionRouter/Actions/InvestigationTableAction.php`

These paths may resolve request metadata by request ID (`rid`) once the caller has already passed the current action authorization checks.

This is currently accepted behavior. It is not treated as a standalone defect while Shield keeps request-meta access inside the existing high-trust admin boundary.

## Deferred Hardening Note: Shared Request-Meta Lookup

The shared request-meta path should be hardened if Shield later decides that request-meta visibility must be narrower than "any authorized Shield admin who knows a valid `rid`".

Future hardening must scope retrieval by the intended authorization boundary, not by `rid` alone.

Examples:

- active site in WordPress multisite
- current investigation subject or scoped view
- a lower-privilege admin role that is allowed to view only a subset of logs

If that change happens, the retrieval boundary should be enforced centrally in the shared request-meta path rather than patched separately in multiple callers.

## Revisit Triggers

Re-open this note if any of the following become true:

- request logs are exposed to sub-site admins in multisite
- request logs or activity logs become site-scoped for access control
- non-Security-Admin users can access any request-meta surface
- investigation scope becomes a real authorization boundary rather than only a UI filter
- request-meta exposure policy is narrowed beyond the current admin-only model

## Relevant Code Paths

- `src/ActionRouter/Actions/HandlesRequestMetaTableAction.php`
- `src/DBs/ReqLogs/GetRequestMeta.php`
- `src/ActionRouter/Actions/ActivityLogTableAction.php`
- `src/ActionRouter/Actions/InvestigationTableAction.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageActivityLogTable.php`
- `assets/js/components/tables/ActivityLogMetaPopover.js`
