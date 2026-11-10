          <div class="card">
            <div class="card-head">
              <div>
                <div class="card-title">Bộ Kiểm Thử Tự Động Toàn Diện Hệ Thống (88 Test Cases)</div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                  Thực thi lệnh kiểm thử <code>bash lab.sh test</code> trực tiếp trên môi trường máy chủ để xác nhận tính toàn vẹn 100% của hệ thống.
                </div>
              </div>
              <div>
                <a href="/?action=run_full_test_suite" onclick="return confirmWithHold(event, { title: 'Chạy Bộ 88 Kiểm Thử', message: 'Khởi chạy toàn bộ 88 kịch bản kiểm thử bảo mật & phân quyền trực tiếp trên môi trường máy chủ?' })" class="btn btn-primary">
                  Chạy Toàn Bộ Test Suite (bash lab.sh test)
                </a>
              </div>
            </div>

            <div style="background: #090d16; border-radius: 8px; padding: 16px; font-family: monospace; color: #22c55e; font-size: 12px; line-height: 1.6; max-height: 480px; overflow-y: auto;">
              <?php
                if (isset($_SESSION['last_full_test_output']) && str_contains((string)$_SESSION['last_full_test_output'], 'not found')) {
                    unset($_SESSION['last_full_test_output']);
                }
              ?>
              <?php if (isset($_SESSION['last_full_test_output'])): ?>
                <pre style="white-space: pre-wrap; margin: 0; color: #38bdf8;"><?= htmlspecialchars($_SESSION['last_full_test_output']) ?></pre>
              <?php else: ?>
                <div style="color: #94a3b8;">
                  # Nhấn vào nút "Chạy Toàn Bộ Test Suite" phía trên để thực thi kiểm thử toàn bộ 88 ca kiểm tra.<br>
                  # Bao gồm: Unit test CSDL PostgreSQL, Kiểm tra chính sách ABAC/RBAC, E2E HTTP Test và Ma trận bảo mật.
                </div>
              <?php endif; ?>
            </div>
          </div>
