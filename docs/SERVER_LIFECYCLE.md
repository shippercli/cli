# Server Lifecycle

Shipper can target either:

- an existing provider server
- a server created on demand for a profile

The generic shape lives under `profiles.<name>.infrastructure.server`.

## Existing server

```yaml
providers:
  ploi:
    api_key: ${PLOI_API_KEY}

projects:
  api:
    provider: ploi
    profiles:
      production:
        domain: "api.example.com"
        infrastructure:
          server:
            mode: existing
            id: "123456"
```

## Created server

```yaml
providers:
  ploi:
    api_key: ${PLOI_API_KEY}

projects:
  api:
    provider: ploi
    profiles:
      preview:
        domain: "preview.example.com"
        infrastructure:
          server:
            mode: create
            cleanup: destroy
            ttl: 72h
            spec:
              name: "api-pr-${PR_NUMBER}"
              credential: "42"
              region: "fra1"
              plan: "vc2-1c-2gb"
              php_version: "8.3"
```

## Core fields

- `mode`
- `id`
- `cleanup`
- `ttl`
- `spec`

## Cleanup policies

- `destroy`: remove the created server during cleanup flows
- `retain`: leave it running
- `manual`: leave it running and require explicit human cleanup

## TTL expiry

For a create-mode profile with `cleanup: destroy`, `ttl` sets the maximum age of the managed server. Use a positive number of seconds or a duration such as `72h`, `30m`, or `7d`. Expiry is measured from the provider-reported server creation time; providers that do not return that timestamp cannot be automatically expired.

The `servers:cleanup-expired` command is scheduled every minute by Shipper. The host must run Shipper's Laravel scheduler (`schedule:run` every minute, or a persistent `schedule:work` process), and `SHIPPER_CONFIG` must identify the configuration file when it is not `shipper.yml` in the working directory. On expiry, Shipper runs the normal destroy flow and deletes the managed server only when cleanup ownership is proven.

Providers whose APIs cannot safely remove a linked billable cloud instance are skipped before application resources are destroyed. Coolify can remove a Shipper-provisioned Hetzner, DigitalOcean, or Vultr instance when the matching cloud-provider token is configured in Coolify and no Coolify resources remain on the server. Existing cloud servers resolved through Coolify remain record-only and are not removed by Shipper cleanup.

## Ownership and cleanup safety

For providers with limited metadata support, Shipper may prove ownership using a deterministic managed naming convention.

Current Ploi behavior creates servers with a managed name derived from:

- `shipper`
- project name
- profile name
- configured server name

Cleanup only applies to servers that match the managed name. If only an unmanaged human-facing name exists, cleanup is refused.

## Ploi create-mode requirements

Current Ploi support requires these `spec` values:

- `name`
- `credential` or `provider_id` or `provider`
- `region`
- `plan` or `size`

Additional `spec` keys are passed through to the provider API.
