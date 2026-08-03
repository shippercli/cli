# Shipper CLI Build and Distribution

This document explains the complete build and distribution system for the Shipper CLI.

## Overview

The Shipper CLI can be distributed and used in three ways:

1. **Local Development**: Run from source with `./shipper`
2. **Pre-built Binary**: Download from GitHub releases
3. **GitHub Action**: Use in workflows via the reusable action

## Build System

### Box Configuration (`box.json`)

The build system uses [Box](https://github.com/box-project/box) to create a PHAR (PHP Archive) binary:

- **Input**: PHP source code + dependencies
- **Output**: Single executable file (`builds/shipper`)
- **Compression**: GZ compression for smaller file size
- **Contents**: All app code, config, routes, and vendor dependencies

### Build Process

#### Local Build

```bash
composer build
```

This will:
1. Download Box if not present
2. Compile the PHAR using box.json configuration
3. Output to `builds/shipper`

#### CI Build (GitHub Actions)

The `.github/workflows/build-release.yml` workflow automatically builds and releases binaries when a version tag is pushed:

1. **Trigger**: Push a tag like `v1.0.0`
2. **Process**:
   - Install PHP 8.3
   - Install composer dependencies (production only)
   - Download Box
   - Build PHAR
   - Test the binary
   - Create GitHub release
   - Attach binary to release

**Example**:
```bash
git tag v1.0.0
git push origin v1.0.0
```

This creates a release at: `https://github.com/shippercli/cli/releases/tag/v1.0.0`

## Distribution

### GitHub Releases

Each tagged version creates a release with the binary attached. Users can download with:

```bash
# Latest version
curl -LSso shipper https://github.com/shippercli/cli/releases/latest/download/shipper
chmod +x shipper

# Specific version
curl -LSso shipper https://github.com/shippercli/cli/releases/download/v1.0.0/shipper
chmod +x shipper
```

### Reusable GitHub Action

The `.github/actions/shipper/action.yml` provides a reusable action that:

1. Downloads the binary from releases
2. Verifies it's executable and valid
3. Runs the specified shipper command
4. Returns the exit code

**Usage in other repositories**:

```yaml
- uses: shippercli/cli/.github/actions/shipper@main
  with:
    command: apply
    project: api
    profile: production
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Security

### Build Process
- Dependencies are locked via `composer.lock`
- Production dependencies only (--no-dev)
- Code is compressed but not obfuscated

### Action Security
- No eval or shell injection vulnerabilities
- User inputs handled with bash arrays
- Binary verification before execution
- Downloads from official GitHub releases only

## File Structure

```
.
├── box.json                                    # Box configuration
├── composer.json                               # Build script
├── .github/
│   ├── workflows/
│   │   └── build-release.yml                  # Build automation
│   └── actions/
│       └── shipper/
│           ├── action.yml                     # Reusable action
│           └── README.md                      # Action documentation
└── builds/                                    # Build output (gitignored)
    └── shipper                                # Compiled binary
```

## Version Management

### Semantic Versioning

Use semantic versioning for releases:
- **Major** (v2.0.0): Breaking changes
- **Minor** (v1.1.0): New features, backward compatible
- **Patch** (v1.0.1): Bug fixes

### Tagging Process

```bash
# Create and push tag
git tag v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0

# The build-release.yml workflow automatically:
# 1. Builds the binary
# 2. Creates a GitHub release
# 3. Uploads the binary
```

### Action Versioning

Users can reference the action by:
- **Branch**: `@main` (latest development)
- **Tag**: `@v1.0.0` (specific version)
- **Commit**: `@939e086` (specific commit)

**Recommendation**: Use tags for stability in production workflows.

## Troubleshooting

### Build Failures

**Problem**: Build fails with missing dependencies
```
Class "LaravelZero\Framework\Application" not found
```

**Solution**: Ensure `composer install` runs successfully first. The vendor directory must be complete before building.

**Problem**: Box validation fails
```
The configuration file failed validation
```

**Solution**: Validate the configuration:
```bash
php box.phar validate
```

### Action Failures

**Problem**: Binary download fails
```
Error: Failed to download or make shipper executable
```

**Solution**: 
- Check the release exists at the specified version
- Verify GitHub Actions has internet access
- Check if the release has the binary attached

**Problem**: Command fails with wrong arguments
```
Command "aplpy" is not defined
```

**Solution**: Check the command name is correct (validate, plan, apply)

## Development Workflow

### Making Changes

1. Make code changes
2. Test locally: `./shipper validate`
3. Commit and push
4. CI runs tests and linting
5. Merge to main

### Creating a Release

1. Update version in `config/app.php` if needed
2. Update CHANGELOG (if maintained)
3. Create and push tag:
   ```bash
   git tag v1.x.x
   git push origin v1.x.x
   ```
4. GitHub Actions builds and releases automatically
5. Verify release appears with binary attached

### Testing the Action

Create a test workflow in any repository:

```yaml
name: Test Shipper Action
on: workflow_dispatch

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shippercli/cli/.github/actions/shipper@main
        with:
          command: validate
```

## Future Enhancements

Potential improvements to the build system:

1. **Checksums**: Generate and publish SHA256 checksums for binaries
2. **Code Signing**: Sign binaries for additional trust
3. **Multi-platform**: Build for different PHP versions or architectures
4. **Homebrew**: Create a Homebrew formula for easy installation
5. **Docker**: Provide Docker images with the binary pre-installed
6. **Versioning in Binary**: Embed git commit hash in --version output

## References

- [Box Documentation](https://github.com/box-project/box/blob/main/doc/configuration.md)
- [Laravel Zero Documentation](https://laravel-zero.com/)
- [GitHub Actions - Composite Actions](https://docs.github.com/en/actions/creating-actions/creating-a-composite-action)
- [GitHub Actions - Releasing](https://docs.github.com/en/actions/learn-github-actions/essential-features-of-github-actions#creating-releases)
