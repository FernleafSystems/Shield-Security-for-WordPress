# Shield MCP Operator Usage

Last Updated: 2026-03-14

## Summary

Shield exposes a narrow read-only MCP surface through WordPress Abilities when the runtime supports it.

The current Shield MCP path requires all of the following:

1. WordPress `>= 7.0`
2. WordPress Abilities API available
3. Shield active with Shield REST API Level 2 capability available
4. Official WordPress MCP Adapter runtime available
5. A dedicated authenticated WordPress operator account using an Application Password over HTTPS

If any of those requirements are missing, Shield does not expose its MCP server.

## Exposed Shield Abilities

Shield registers exactly these four read-only abilities:

1. `shield/posture/overview/get`
2. `shield/posture/attention/get`
3. `shield/activity/recent/get`
4. `shield/scan/findings/get`

Shield exposes only that allowlist through MCP.

Important limits:

1. This v1 surface is read-only.
2. It does not expose generic Shield action routing.
3. It does not trigger repairs, option changes, unblock actions, or scan launches.

## Operator Account

Create a dedicated WordPress user for external operations:

1. Create a separate user for Shield operations only.
2. Assign the `Administrator` role, or an equivalent custom role that satisfies Shield's current permission model.
3. Confirm the account can pass both `manage_options` and Shield `rest_api_level_2`.
4. Do not reuse a personal administrator account if a dedicated operator identity is practical.

## Application Password

Use a WordPress Application Password for the operator account:

1. Sign in as the dedicated operator user.
2. Open the user profile page in WordPress admin.
3. Generate a new Application Password for the MCP client.
4. Store it in the client secret store used for the site connection.
5. Use HTTPS only.

Treat the Application Password as a live credential:

1. Rotate it if operator access changes.
2. Revoke it if a client machine or secret store is compromised.

## Runtime Behavior

Shield owns the business contract and the allowlist. The MCP transport remains separate.

Current runtime behavior:

1. Shield registers the four abilities only when the WordPress 7.0+ compatibility checks pass.
2. Shield registers an MCP server only when the official WordPress MCP Adapter runtime is present.
3. The current server definition uses `server_id: shield-security`, `namespace: shield-security`, and `route: mcp`.
4. Shield does not rely on the adapter's default-server discovery behavior.

The exact published MCP endpoint is transport-owned by the adapter/runtime. The Shield-owned contract is the server identity plus the four-ability allowlist above.

## Ability Inputs

The first three abilities take no input.

`shield/scan/findings/get` accepts these optional array inputs:

1. `scan_slugs`
2. `filter_item_state`

Supported `filter_item_state` values:

1. `is_checksumfail`
2. `is_unrecognised`
3. `is_mal`
4. `is_missing`
5. `is_abandoned`
6. `is_vulnerable`

## Supported v1 Questions

This MCP surface is intended to answer questions such as:

1. What needs attention on my site?
2. What is the current security posture overview?
3. What changed recently?
4. What did the latest scans find?

## Expected Failure Modes

Expected operator-visible failure modes:

1. WordPress below 7.0: no Shield MCP exposure
2. Abilities API unavailable: no Shield MCP exposure
3. MCP Adapter runtime unavailable: no Shield MCP server exposure
4. Missing `manage_options` or Shield `rest_api_level_2`: permission denied
5. Scans currently running: scan findings return unavailable state rather than implying there are no findings

## REST Fallback

If Shield MCP is unavailable, the same canonical read-only query layer remains available through Shield REST:

1. `GET /shield/v1/posture/overview`
2. `GET /shield/v1/posture/attention`
3. `GET /shield/v1/activity/recent`
4. `GET /shield/v1/scan_results`
