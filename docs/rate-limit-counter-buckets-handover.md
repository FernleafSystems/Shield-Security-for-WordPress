# Rate Limit Counter Bucket Handover

Date: 2026-04-29
Status: Deferred; not part of the current audit/request logging performance slice.

## Current Behavior

Develop can log plain no-param requests such as `GET /` when traffic rate limiting is enabled. This is not caused by an audit event.

The path is:

1. `ShieldLogRequest` sets `shield/is_log_traffic=true` when `ShieldConfigIsTrafficRateLimitingEnabled` matches.
2. `RequestLogger` writes a visible `req_logs` row.
3. `IsRateLimitExceeded` counts recent `req_logs` rows for the IP.

Therefore rate limiting cannot stop writing visible request-log rows while it still uses `req_logs` as its counter source.

## Rejected Approach

Do not use a raw per-request table like `(ip_ref, request_at)` unless exact sliding-window counting becomes mandatory. It is cheaper than `req_logs`, but still grows linearly with every request and can balloon during high traffic or attacks.

Also avoid a rolling update that moves a row timestamp forward on every request. That turns the row into a continuous lifetime counter and prevents old requests from aging out correctly.

## Preferred Future Design

Use a fixed bucket counter table:

- `ip_ref`
- `bucket_start_at`
- `request_count`

Use a unique index on `(ip_ref, bucket_start_at)` and a cleanup-friendly index on `bucket_start_at`.

For each rate-limited request, calculate:

```php
$bucketStart = floor( $now / $bucketSize ) * $bucketSize;
```

Then write with one upsert:

```sql
INSERT INTO rate_limit_buckets (ip_ref, bucket_start_at, request_count)
VALUES (?, ?, 1)
ON DUPLICATE KEY UPDATE request_count = request_count + 1
```

The limit check should sum all buckets overlapping the configured window. This prevents count reset at bucket boundaries.

## Tradeoff

Bucket counting is approximate at window edges. With 5-second buckets, the limiter may overcount by up to one bucket. That is acceptable for abuse control and prevents table growth from scaling one row per request.

## Future Acceptance Criteria

- Rate limiting no longer requires visible `req_logs` rows for plain no-param requests.
- Live logging, audit-dependent logs, parameterized requests, and offenses can still create visible request-log rows.
- Rate limiting still trips from bucket counts.
- Bucket-boundary tests prove counts do not reset to zero at the start of a new bucket.
- Cleanup removes buckets older than the configured rate-limit window plus a small bucket-size buffer.
