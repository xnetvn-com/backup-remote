# Copyright (c) 2025 xNetVN Inc.
# Website: https://xnetvn.com/
# License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
# Contact: license@xnetvn.net
#
# Feature request template
---
name: "Feature Request"
description: "Propose a new feature or improvement for the system"
title: "[FEATURE] <short description>"
labels: [enhancement]
assignees: [xnetvn-admin]
body:
  - type: markdown
    attributes:
      value: |
        Please describe clearly the need and value of the proposed feature.
  - type: input
    id: summary
    attributes:
      label: Feature Summary
      description: Briefly describe the desired feature
    validations:
      required: true
  - type: textarea
    id: motivation
    attributes:
      label: Motivation/Value
      description: Why is this feature needed? What value does it bring?
    validations:
      required: true
  - type: textarea
    id: proposal
    attributes:
      label: Proposed Solution
      description: Suggest a specific implementation or idea
    validations:
      required: false
  - type: input
    id: env
    attributes:
      label: Related Environment
      description: OS, PHP version, ... (if relevant)
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
