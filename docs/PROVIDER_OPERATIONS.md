# Provider operations

Shipper's required provider contract covers validation, planning, apply, and
destroy. Provider packages can additionally implement deployment status, logs,
rollback, and server lifecycle contracts.

Commands fail clearly when the selected provider does not implement the
requested optional capability.

## Status

```bash
shipper status app --profile=production
```

Status prints provider-defined JSON. Providers should report the requested
project/profile identity, deployment state, and only the resource information
they can retrieve with the configured account.

An unavailable optional API should be represented in the status payload when
possible instead of hiding all other state.

## Logs

```bash
shipper logs app --profile=production --lines=100
```

Logs prints recent provider or application log lines. `--lines` accepts values
from `1` through `5000`. Availability and source depend on the provider and
hosting plan.

## Rollback

```bash
shipper rollback app --profile=production
shipper rollback app --profile=production --release=release-id
```

Without `--release`, the provider selects its latest restorable release.
Rollback asks for confirmation unless `--force` is supplied. Providers are
responsible for validating release identifiers and refusing paths or resources
owned by another deployment.

## Destroy

```bash
shipper destroy app --profile=preview
```

Destroy is part of the required deployment contract. Providers should remove
only resources they can prove were created or adopted by the matching Shipper
deployment. Destructive CI jobs use `--force`; interactive use keeps the
confirmation prompt.

## Provider-specific behavior

Provider packages own their configuration keys, feature matrix, hosting-plan
limitations, and operational details. Consult the selected provider's package
documentation before enabling cleanup or rollback in production.
