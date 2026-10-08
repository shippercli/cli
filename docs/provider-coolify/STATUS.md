# Session Status

Updated: 2026-10-08

## Active objective

Finish the Coolify provider so it supports Shipper core deployment capabilities wherever Coolify's API supports them. Keep genuine API limitations explicit; do not claim full parity based only on a capability manifest or README.

## Current checkout state

- `provider-coolify` already maps Shipper's project-level `nginx_config` into Coolify's `custom_nginx_configuration` for Git-based Static applications and Git-based Nixpacks/Railpack applications with `coolify.application.is_static: true`.
- The provider rejects that setting for unsupported source/build combinations, and respects an explicit application-level `custom_nginx_configuration` override.
- The README documents this mapping. Relevant implementation references: `provider-coolify/src/CoolifyProvider.php:202-204`, `:750-752`, `:4500-4502`, `:7267`; docs: `provider-coolify/README.md:333`.
- A current search found no explicit `custom_nginx_configuration` assertion in `provider-coolify/tests/CoolifyProviderTest.php`; the test fixture does expose `nginxConfig()`. Add a focused assertion before treating this behavior as regression-covered.
- Coolify fixed the API base64 decoding issue in v4.1.0, PR [#9841](https://github.com/coollabsio/coolify/pull/9841) (fix #10067). A live Coolify app is not required for a mocked provider payload test.
- Recent provider work reflected in this checkout includes shown-once secret environment variables, storage validation, and richer plan output.

## Remaining known gaps / constraints

- Coolify's public API does not document CRUD for logical backup schedules on databases embedded in Compose Services. Service storage-volume backups are available but are not equivalent to database-aware dumps.
- Shipper network allow/deny rules have no equivalent Coolify API resource; do not emulate firewall semantics with unrelated published port settings.
- Continue a core-contract-to-provider audit for any additional unimplemented mappings, lifecycle operations, and test gaps. The broad goal is not complete.

## Verification state

- No tests were run during the 2026-10-08 handoff capture.
- The overall Coolify-provider goal was mistakenly marked complete by the goal tracker in a prior turn; that status was incorrect. Treat the objective as unfinished and continue from `GOAL-PROMPT.md` and `TODO.md`.
