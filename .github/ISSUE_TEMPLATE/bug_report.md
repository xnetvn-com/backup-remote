# Copyright (c) 2025 xNetVN Inc.
# Website: https://xnetvn.com/
# License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
# Contact: license@xnetvn.net
#
# Bug report template
---
name: "Báo cáo lỗi (Bug Report)"
description: "Báo cáo lỗi, sự cố hoặc hành vi không đúng của hệ thống"
title: "[BUG] <mô tả ngắn gọn>"
labels: [bug]
assignees: [xnetvn-admin]
body:
  - type: markdown
    attributes:
      value: |
        Vui lòng điền đầy đủ thông tin để giúp chúng tôi xử lý nhanh nhất.
  - type: input
    id: summary
    attributes:
      label: Tóm tắt lỗi
      description: Mô tả ngắn gọn về lỗi gặp phải
    validations:
      required: true
  - type: textarea
    id: steps
    attributes:
      label: Các bước tái hiện
      description: Mô tả chi tiết các bước để tái hiện lỗi
    validations:
      required: true
  - type: textarea
    id: expected
    attributes:
      label: Kết quả mong đợi
      description: Hệ thống nên hoạt động như thế nào?
    validations:
      required: true
  - type: textarea
    id: actual
    attributes:
      label: Kết quả thực tế
      description: Hệ thống thực tế đã làm gì?
    validations:
      required: true
  - type: input
    id: env
    attributes:
      label: Môi trường
      description: OS, PHP version, browser, ...
    validations:
      required: false
  - type: textarea
    id: logs
    attributes:
      label: Log, ảnh chụp màn hình
      description: Đính kèm log hoặc ảnh minh họa nếu có
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
