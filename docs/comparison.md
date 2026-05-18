# Comparison with Other Tools

## Overview

No single tool covers the full picture of dependency health. Package Doctor is designed to be the **decision layer** on top of the data Composer and other tools already provide.

## Capability Matrix

| | `composer outdated` | `composer audit` | Dependabot / Renovate | Roave + Enlightn | Laravel Package Doctor |
|---|:---:|:---:|:---:|:---:|:---:|
| Detect outdated versions | ✓ | – | ✓ (PRs) | – | ✓ |
| Security advisories | – | ✓ | ✓ (alerts) | ✓ | ✓ |
| Abandoned packages | – | ✓ (partial) | – | – | ✓ |
| Repository archived detection | – | – | – | – | ✓ |
| Laravel version compatibility | – | – | – | – | ✓ |
| PHP version compatibility | – | – | – | – | ✓ |
| Constraint-blocked upgrade detection | – | – | – | – | ✓ |
| License risk classification | – | – | – | – | ✓ |
| Health score (0–100) | – | – | – | – | ✓ |
| Actionable recommendations | – | – | – | – | ✓ |
| Upgrade-risk classification | – | – | – | – | ✓ |
| CI exit codes | – | ✓ | n/a | – | ✓ |
| Offline mode | ✓ | ✓ | – | – | ✓ |
| Laravel Artisan command | – | – | – | – | ✓ |

## Tool-by-Tool Breakdown

### `composer outdated`

The authoritative source for version availability. Tells you a newer version exists, but not what it means. No health scoring, no compatibility checks, no recommendations.

**When to use:** To see a quick diff of installed vs latest.
**Use alongside Package Doctor:** Yes. Package Doctor calls `composer outdated` internally.

### `composer audit`

Reports known CVEs from the Packagist security advisories database and flags packages marked abandoned (since Composer 2.6). Essential, but limited to security and abandonment signals only.

**When to use:** As a baseline security gate in CI.
**Use alongside Package Doctor:** Package Doctor calls `composer audit` internally and surfaces its findings in the scored report.

### Dependabot / Renovate

Bot services that open pull requests when new versions are available and send alerts for security issues. They automate the *update action*, not the *analysis decision*. They have no awareness of Laravel version compatibility, upgrade risk classification, or whether a package is a good upgrade candidate at a specific point in the Laravel upgrade cycle.

**When to use:** For automated dependency bumps in active projects.
**Use alongside Package Doctor:** Yes. Use bots to automate safe updates; use Package Doctor to understand what needs human attention before a major upgrade.

### Roave Security-Advisories + Enlightn Security-Checker

`roave/security-advisories` is a Composer constraint that *prevents installing* packages with known CVEs, rather than reporting on them. Enlightn Security-Checker reports CVEs via a local or remote advisories database.

Both are security-only tools with no upgrade-risk analysis, no compatibility checking, and no scoring.

**When to use:** As a hard install-time guard (`roave/security-advisories`) or a CI security scan step.
**Use alongside Package Doctor:** Yes, they are complementary. Package Doctor surfaces the same CVEs in context with a full risk profile.

### composer-unused / composer-require-checker

Different category entirely. These tools find packages in your `composer.json` that your code doesn't actually use (`composer-unused`), or packages your code uses that aren't directly required (`composer-require-checker`). They're static analysis tools for dependency hygiene, not health or risk analysis.

**Use alongside Package Doctor:** Yes. Clean up your dependency list with these tools first, then run Package Doctor on what remains.

## Summary

> Use Dependabot/Renovate to *automate* routine updates.
> Use `composer audit` and `roave/security-advisories` as hard security gates.
> Use Package Doctor to *understand* what your dependencies mean before a Laravel upgrade, when inheriting a project, or when you want a scored, actionable risk report instead of a raw list of changes.

These tools are not mutually exclusive. Package Doctor composes all the data they produce (Composer outdated, audit, licenses) into a single scored report with recommendations.
