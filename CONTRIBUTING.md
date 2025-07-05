# Copyright (c) 2025 xNetVN Inc.
# Website: https://xnetvn.com/
# License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
# Contact: license@xnetvn.net
#
# CONTRIBUTING.md - Contribution Guide for xNetVN Inc. Project

## General Rules

- All contributions must comply with xNetVN Inc. standards for programming, security, testing, and DevOps.
- Use English for code and documentation, Vietnamese for user communication only.
- Do not commit secrets, sensitive information, build files, or logs.
- **It is strictly forbidden to perform any write, delete, move, or overwrite operations on files or directories inside `BACKUP_DIRS`. All backup, compression, and encryption operations must be performed on temporary copies only. The source data in `BACKUP_DIRS` must always remain read-only and unchanged.**

## Contribution Process

1. Fork and clone the repository.
2. Create a branch following GitFlow convention: `feature/&lt;feature-name&gt;`, `bugfix/&lt;bug-description&gt;`, ...
3. Write code, tests, and documentation fully, following the style guide.
4. Run all tests, lint, coverage, SAST, and secret scanning before creating a PR.
5. Create a Pull Request, link to the relevant Issue, and clearly describe the changes and reasons.
6. Wait for review and revise according to feedback if needed.
7. Only merge when CI/CD passes and the PR is approved.

## Contact

- Email: [license@xnetvn.net](mailto:license@xnetvn.net)
- Website: [https://xnetvn.com/](https://xnetvn.com/)
