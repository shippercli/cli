# Resume Goal

```text
/goal make a Coolify provider similar to our other providers. Don’t stop until Coolify supports everything Shipper core requires that Coolify's API can support.
```

Continue from `STATUS.md`, `TODO.md`, and `COOLIFY-SESSION-CONTEXT.md`. Inspect the current checkout as authoritative. Preserve existing changes. Build and maintain a requirement-by-requirement matrix across Shipper core configuration/contracts, Coolify's current public API, provider implementation, documentation, and tests. Implement real supported gaps, add regression coverage, and keep API-impossible capabilities explicitly documented rather than claiming parity or guessing endpoints.

The project-level `nginx_config` mapping is already present in the current checkout. Do not reimplement it. Add a test that verifies the exact Coolify request payload and its supported/unsupported source/build combinations. Coolify fixed the custom Nginx API payload bug in v4.1.0 (Coolify PR #9841 / fix #10067); document or enforce any minimum version needed for safe use.

Do not mark this goal complete until the full core/API/provider audit is evidenced and relevant tests pass. A live Coolify instance is not required for mock-based API contract coverage; request live access only if an unresolved behavior cannot be validated locally.
```
