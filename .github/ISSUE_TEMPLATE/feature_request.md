# Copyright (c) 2025 xNetVN Inc.
# Website: https://xnetvn.com/
# License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
# Contact: license@xnetvn.net
#
# Feature request template
---
name: "Yêu cầu tính năng (Feature Request)"
description: "Đề xuất tính năng mới hoặc cải tiến cho hệ thống"
title: "[FEATURE] <mô tả ngắn gọn>"
labels: [enhancement]
assignees: [xnetvn-admin]
body:
  - type: markdown
    attributes:
      value: |
        Vui lòng mô tả rõ nhu cầu và giá trị của tính năng đề xuất.
  - type: input
    id: summary
    attributes:
      label: Tóm tắt tính năng
      description: Mô tả ngắn gọn về tính năng mong muốn
    validations:
      required: true
  - type: textarea
    id: motivation
    attributes:
      label: Động lực/giá trị
      description: Tại sao cần tính năng này? Giá trị mang lại?
    validations:
      required: true
  - type: textarea
    id: proposal
    attributes:
      label: Đề xuất giải pháp
      description: Đề xuất cách thực hiện hoặc ý tưởng cụ thể
    validations:
      required: false
  - type: input
    id: env
    attributes:
      label: Môi trường liên quan
      description: OS, PHP version, ... (nếu liên quan)
    validations:
      required: false
  - type: dropdown
    id: priority
    attributes:
      label: Mức độ ưu tiên
      options:
        - Thấp
        - Trung bình
        - Cao
    validations:
      required: true
