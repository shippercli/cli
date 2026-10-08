# Coolify Provider TODO

## Immediate

- [ ] Add explicit regression coverage for `ProjectConfig::nginxConfig()` becoming `custom_nginx_configuration` in the Coolify application create/update payload.
- [ ] Cover validation boundaries: Git + Static build pack; Git + Nixpacks/Railpack with `coolify.application.is_static: true`; rejection for other source/build combinations; explicit application option precedence.
- [ ] Confirm and document the minimum Coolify version for safe Nginx API writes (upstream fix shipped in v4.1.0, PR #9841 / fix #10067).
- [ ] Run the focused Coolify provider test suite after the regression test is added.

## Provider parity audit

- [ ] Build a traceable matrix for every `ProjectConfig`, `ProfileConfig`, provider contract capability, and Shipper deployment operation: supported mapping, explicit validation rejection, or genuine Coolify API limitation.
- [ ] Inspect worker/queue/daemon, cron, redirects, SSL/domain, environment, database, storage, previews, rollback, logs/status, and server lifecycle paths against the current Coolify API docs and tests.
- [ ] For every supported path, ensure tests assert externally observable API payloads and cleanup/ownership behavior, not only successful method return values.
- [ ] Reconcile README examples and capability-manifest states with implementation and the completed matrix.

## Known API limits to revisit when Coolify changes

- [ ] Recheck for public REST support for logical backup schedule CRUD on databases inside Compose Services. Until then, do not substitute storage-volume backups for database dumps.
- [ ] Recheck for a Coolify API model equivalent to Shipper network allow/deny rules. Do not map these to unrelated port exposure settings.

## Completion gate

- [ ] No Shipper core operation is silently ignored: each is implemented, explicitly rejected with an actionable reason, or documented as unsupported by Coolify's public API.
- [ ] All implementation and documentation claims have focused test or authoritative API evidence.
- [ ] Run the relevant provider tests and report any live Coolify integration behaviors that remain unverified.
