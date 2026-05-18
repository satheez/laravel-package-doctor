# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 1.x     | Yes       |

## Reporting a Vulnerability

Please do **not** open a public GitHub issue for security vulnerabilities.

Report vulnerabilities privately by emailing: **mysatheez@gmail.com**

Include:
- A description of the vulnerability
- Steps to reproduce
- The version(s) affected
- Any suggested fix if you have one

You will receive a response within 72 hours. If the report is confirmed, a fix will be prepared and released as soon as possible.

## Scope

Security issues in this package typically concern:
- GitHub token exposure (token must never appear in logs, cache, or JSON output)
- Command injection via Composer binary path configuration
- Path traversal via composer.json/lock path configuration
