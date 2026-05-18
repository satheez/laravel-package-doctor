# Contributing

Thank you for your interest in contributing to Laravel Package Doctor.

## Local Setup

```bash
git clone https://github.com/satheez/laravel-package-doctor.git
cd laravel-package-doctor
composer install
```

## Running Tests

```bash
vendor/bin/pest
```

## Code Style

```bash
vendor/bin/pint
```

To check without fixing:

```bash
vendor/bin/pint --test
```

## Static Analysis

```bash
vendor/bin/phpstan analyse
```

## Automated Refactoring (Rector)

Check for suggested changes (dry-run):

```bash
vendor/bin/rector process --dry-run
```

Apply changes:

```bash
vendor/bin/rector process
```

Rector enforces PHP 8.2 modernization, dead code removal, type declaration completeness, and early-return patterns. Run it before opening a PR and commit any changes it produces.

## Branch Naming

- `feature/<name>` — new features
- `fix/<name>` — bug fixes
- `chore/<name>` — tooling, dependency updates, documentation

## Pull Request Guidelines

- Keep PRs focused on a single concern.
- All new code must be covered by tests.
- PHPStan must pass at level 8.
- Pint formatting must be clean.
- Rector dry-run must produce no changes.
- No real HTTP calls or Composer subprocess calls in tests. Use stubs.
- Add an entry to `CHANGELOG.md` under `[Unreleased]`.

## Testing Expectations

- Unit tests: pure class logic, no filesystem, no HTTP, no subprocess.
- Feature tests: use Orchestra Testbench, fixture files, and stub the `ComposerProcessContract`.
- HTTP: use Guzzle `MockHandler` + `HandlerStack`.
- Cache: use `Illuminate\Cache\ArrayStore`.
- No `Mockery::mock()` on `final` classes — use anonymous class stubs implementing the contract interface.
