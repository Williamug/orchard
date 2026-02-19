[![Tests](https://github.com/Williamug/orchard/actions/workflows/test.yml/badge.svg)](https://github.com/Williamug/orchard/actions/workflows/test.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

# 🍃 Orchard

**Orchard** is a lightweight, high-performance PHP CLI tool designed for safe, centralized bulk dependency maintenance across multiple Laravel projects.

Whether you manage a handful of sites or a massive fleet of Laravel applications, Orchard provides a unified interface to scan, audit, and update them all without ever risking your data.

---

## Key Features

*   **Smart Probing** — Instantly scan directories (flat or recursive) to discover Laravel projects.
*   **Unified Status** — Get a high-level table view of Laravel versions, PHP constraints, Git status, and Composer versions across your entire fleet.
*   **Outdated Reporting** — Aggregate `composer outdated` results from every project into a single, scanable report.
*   **Git Guard** — Built-in safety enforcement. Orchard **refuses** to update any project with uncommitted git changes, ensuring you never lose local work.
*   **Auto-Branching** — Automatically create dated Git branches (e.g., `chore/orchard-update-2026-02-19`) before running updates, following best-practice deployment workflows.
*   **Parallel Processing** — High-speed concurrent updates powered by Symfony Process, with smart CPU-aware throttling.
*   **CI/CD Ready** — Every command supports a `--json` flag for easy integration with automation scripts and monitoring dashboards.

---

## Installation

### From Source
```bash
git clone https://github.com/your-org/orchard.git
cd orchard
composer install --no-dev
chmod +x bin/orchard
```

### Build as PHAR (Recommended for Global Use)
Building Orchard as a PHAR makes it portable and easy to use globally.
```bash
# Build the PHAR
php vendor/bin/box compile

# Move to your global bin
mv dist/orchard.phar /usr/local/bin/orchard
chmod +x /usr/local/bin/orchard
```

---

## Usage Guide

### 1. Finding Projects (`scan`)
Use `scan` to verify which projects Orchard detects in a given directory.
```bash
# Scan current directory
orchard scan

# Scan a specific path recursively
orchard scan --path=/var/www --recursive

# Output results as JSON
orchard scan --json
```

### 2. Auditing Health (`status`)
The `status` command provides a bird's-eye view of your project versions and Git hygiene.
```bash
orchard status

# Check a specific path
orchard status -p /sites
```

### 3. Checking for Updates (`outdated`)
Running `outdated` aggregates all direct dependency updates available across your projects.
```bash
orchard outdated

# Great for checking specialized project clusters
orchard outdated --path=~/sites/client-a --exclude=legacy-cms
```

### 4. Bulk Maintenance (`update`)
The core of Orchard. Safely run `composer update` across your fleet.
```bash
# Update all clean projects in parallel
orchard update --parallel=4

# The "Safety First" Workflow: Dry run + Auto-branch
orchard update --auto-branch --dry-run

# Full Automation with specific branch prefix
orchard update --auto-branch --branch-prefix=security-fix --parallel=2
```

---

## Configuration

Set persistent defaults by creating a `~/.orchard.json` file. This is ideal for excluding legacy projects or setting your default projects path.

```json
{
  "base_path": "/home/user/sites",
  "parallel": 4,
  "recursive": true,
  "exclude": ["abandoned-project", "experimental-v3"],
  "auto_branch": false,
  "branch_prefix": "chore/deps-update",
  "timeout": 300
}
```

**Resolution Priority:**
1.  Explicit CLI Flags (e.g., `--parallel=10`)
2.  `~/.orchard.json` values
3.  Internal Defaults (1 process, current directory)

---

## The Orchard Safety Pledge

Orchard is built for developers who care about stability. We follow strict safety rules:

1.  **Strict Git Isolation**: If `git status` shows uncommitted changes, Orchard skips the project. No exceptions.
2.  **No Constraint Tampering**: Orchard runs `composer update`. It **never** touches your `composer.json` version ranges.
3.  **No Ghost Commits**: Orchard creates branches (if enabled) but **never** performs `git commit` or `git push`. You remain in control of the final merge.
4.  **Error Isolation**: A crash or failure in one project is isolated. Orchard continues with the rest and provides a summary report at the end.

---

## Exit Codes

| Code | Meaning |
| :--- | :--- |
| `0` | **Success** — All projects updated or skipped safely. |
| `1` | **Warning** — One or more project updates failed. |
| `2` | **Error** — Invalid configuration or system environment issues. |

---

## Development & Testing

We maintain a 100% pass rate on our test suite. To contribute:
```bash
composer install
./vendor/bin/phpunit --testdox
```

---

## License
MIT License. Created by the Orchard Contributors.
