# 🍃 Orchard

**Orchard** is a lightweight PHP CLI tool for safe, centralized bulk dependency maintenance across multiple Laravel projects.

## Features

- **Scan** — Automatically detect all Laravel projects in a directory
- **Status** — Inspect Laravel/PHP/Git/Composer status per project
- **Update** — Run `composer update` safely with Git clean enforcement
- **Git Guard** — Skips dirty repositories — never risks data loss
- **Parallel execution** — Process multiple projects concurrently
- **JSON output** — CI-friendly structured reporting

## Requirements

- PHP 8.2+
- Composer
- Git

## Installation

```bash
git clone https://github.com/your-org/orchard.git
cd orchard
composer install
chmod +x bin/orchard
```

## Usage

### Scan for Laravel projects
```bash
php bin/orchard scan
php bin/orchard scan --path=/home/user/projects
php bin/orchard scan --path=/home/user/projects --recursive
php bin/orchard scan --json
```

### Inspect status
```bash
php bin/orchard status
php bin/orchard status --path=/home/user/projects
php bin/orchard status --json
```

### Bulk update
```bash
php bin/orchard update
php bin/orchard update --path=/home/user/projects
php bin/orchard update --exclude=legacy-app,old-site
php bin/orchard update --parallel=4
php bin/orchard update --dry-run
php bin/orchard update --json
```

## Configuration

Create `~/.orchard.json` to set persistent defaults:

```json
{
  "base_path": "/home/user/projects",
  "parallel": 2,
  "recursive": false,
  "exclude": ["legacy-app"]
}
```

**Priority order:** CLI flags → `~/.orchard.json` → Internal defaults

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | At least one project failed |
| 2 | Configuration or system error |

## Safety Guarantees

- **Never** updates a project with uncommitted git changes
- **Never** modifies `composer.json` version constraints
- **Never** auto-commits or auto-pushes
- **Never** silently destroys data
- Failures in one project do **not** stop other projects

## Output Example

```
🍃 Orchard – updating 3 project(s)

  ✔ api-service [3.2s]
  ⚠ legacy-app (DIRTY_GIT)
  ✖ old-portal (Composer exited with non-zero code)

🍃 Orchard Summary
-------------------
✔ 1 updated
⚠ 1 skipped
✖ 1 failed
Total time: 3s
```

## Building a PHAR

```bash
composer install --no-dev
php vendor/bin/box compile
# Output: dist/orchard.phar
chmod +x dist/orchard.phar
./dist/orchard.phar scan
```

## Running Tests

```bash
composer install
./vendor/bin/phpunit --testdox
```

## Architecture

```
src/
├── Application.php          # Bootstrap, config loading, DI wiring
├── Command/                 # Thin CLI handlers (delegate to services)
│   ├── ScanCommand.php
│   ├── StatusCommand.php
│   └── UpdateCommand.php
├── Service/                 # All business logic
│   ├── LaravelDetector.php
│   ├── ProjectScanner.php
│   ├── GitGuard.php
│   ├── ComposerRunner.php
│   ├── UpdateOrchestrator.php
│   └── Reporter.php
├── DTO/                     # Immutable typed value objects
│   ├── Project.php
│   ├── UpdateResult.php
│   └── StatusResult.php
└── Exception/
    └── OrchardException.php
```

## License

MIT
