# Copyright (c) 2025 xNetVN Inc.
# Website: https://xnetvn.com/
# License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
# Contact: license@xnetvn.net
#
# Bug report template
---
name: "Bug Report"
description: "Report a bug, issue, or unexpected behavior in the system"
title: "[BUG] <short description>"
labels: [bug]
assignees: [xnetvn-admin]
body:
  - type: markdown
    attributes:
      value: |
        Please fill in all required information to help us process your report quickly.
  - type: input
    id: summary
    attributes:
      label: Bug Summary
      description: Briefly describe the encountered bug
    validations:
      required: true
  - type: textarea
    id: steps
    attributes:
      label: Steps to Reproduce
      description: Detailed steps to reproduce the bug
    validations:
      required: true
  - type: textarea
    id: expected
    attributes:
      label: Expected Result
      description: What should the system do?
    validations:
      required: true
  - type: textarea
    id: actual
    attributes:
      label: Actual Result
      description: What did the system actually do?
    validations:
      required: true
  - type: input
    id: env
    attributes:
      label: Environment
      description: OS, PHP version, browser, ...
    validations:
      required: false
  - type: textarea
    id: logs
    attributes:
      label: Logs or Screenshots
      description: Attach logs or screenshots if available
    validations:
      required: false
  - type: dropdown
    id: priority
    attributes:
      label: Priority Level
      options:
        - Low
        - Medium
        - High
    validations:
      required: true
