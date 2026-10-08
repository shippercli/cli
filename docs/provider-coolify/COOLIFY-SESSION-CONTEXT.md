# Coolify Provider Session Context

## Nginx configuration finding

Shipper's project config exposes `ProjectConfig::nginxConfig()`. Current Coolify provider code:

- Validates non-empty project Nginx configuration only for Git-based Static apps or Git-based Nixpacks/Railpack apps with `coolify.application.is_static` enabled.
- Sends it as `custom_nginx_configuration` in the application payload unless that application option was explicitly supplied.
- Rejects unsupported source/build combinations instead of silently dropping the core setting.
- Documents the behavior in the provider README.

Coolify's API previously mishandled this field and could leave Nginx applications crashing. Coolify v4.1.0 fixed decoding and clearing in [PR #9841](https://github.com/coollabsio/coolify/pull/9841), which includes fix #10067 for issue #9975. Do not pass base64-encoded data manually unless the current provider/API contract requires it; the provider currently passes the configuration value directly.

The repository search on 2026-10-08 found the test fixture method `nginxConfig()` but no explicit assertion on the `custom_nginx_configuration` payload field. Add that coverage.

## Existing scope and limits

The provider package already has broad Coolify support for applications, databases, Compose services/components, storage, backups, tasks, secrets/shared variables, previews, logs/status, and server lifecycle. Treat this as orientation, not proof of full parity; complete the matrix in `TODO.md`.

Known gaps/limits based on the inspected provider README/API documentation:

- Logical scheduled database backups for database components inside Compose Services have no documented public REST CRUD; volume-level backups are a different feature.
- Shipper network allow/deny rules do not map to Coolify's published port mappings.
- Coolify public server provisioning supports a limited set of provider catalogs; distinguish cloud provisioning from record-only SSH host registration and ownership-safe cleanup.
- Application runtime behavior and provider-specific limitations are described in `provider-coolify/README.md`; keep this document synchronized with implementation changes.

## Workflow notes

- Use current official Coolify API docs for API-surface questions. Context7 tools were not available in this session; official Coolify docs and upstream Coolify release PRs were used instead.
- No live Coolify app is necessary for unit tests that assert mocked HTTP requests. Ask the user for access only if an integration behavior cannot be established with tests or authoritative docs.
- No tests were run while preparing this handoff.
