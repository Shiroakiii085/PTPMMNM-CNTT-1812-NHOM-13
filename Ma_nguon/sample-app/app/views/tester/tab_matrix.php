          <div class="card">
            <div class="card-head">
              <div>
                <div class="card-title">Ma Trận 24 Ca Kiểm Thử Phân Quyền Thủ Công (Manual Access Matrix)</div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                  Đặt tên cho lần kiểm thử, chủ động khởi chạy toàn bộ 24 ca hoặc bấm kiểm tra từng ca riêng lẻ và lưu vào lịch sử đối chiếu.
                </div>
              </div>
              <div id="matrixTopBadgeWrap">
                <?php if ($executedTestCount === 0): ?>
                  <span style="background: #f1f5f9; color: #475569; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
                    ĐANG CHỜ KIỂM THỬ THỦ CÔNG (0/24 ĐÃ CHẠY)
                  </span>
                <?php elseif ($failedTestCount > 0): ?>
                  <span style="background: #fee2e2; color: #b91c1c; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px;">
                    <?= $passedTestCount ?> PASS / <?= $failedTestCount ?> FAIL <?= $pendingTestCount > 0 ? "($pendingTestCount CHỜ)" : "(R02 Thất Bại do IDOR)" ?>
                  </span>
                <?php else: ?>
                  <span style="background: #dcfce7; color: #15803d; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px;">
                    <?= $passedTestCount ?> PASS / 0 FAIL <?= $pendingTestCount > 0 ? "($pendingTestCount CHỜ)" : "(Tất cả Đạt Chuẩn An Toàn)" ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- BẢNG ĐIỀU KHIỂN ĐẶT TÊN & KHỞI CHẠY KIỂM THỬ THỦ CÔNG -->
            <form method="POST" action="/?action=run_manual_24_tests" onsubmit="return confirmFormWithHold(event, this, { title: 'Xác Nhận Thực Thi & Lưu Kiểm Thử', message: 'Bạn có chắc chắn muốn thực thi và lưu kết quả phiên kiểm thử thủ công này vào lịch sử hệ thống?', badge: 'XÁC NHẬN KIỂM THỬ' });" style="background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%); border: 1px solid #bfdbfe; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
              <input type="hidden" name="exec_mode" id="manualExecModeInput" value="run_all">
              <div style="font-weight: 800; font-size: 13px; color: #1e3a8a; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span>THIẾT LẬP & KHỞI CHẠY LẦN KIỂM THỬ THỦ CÔNG</span>
                <span style="font-size: 11px; font-weight: 600; color: <?= $bugIdor ? '#dc2626' : '#15803d' ?>; background: #fff; padding: 3px 10px; border-radius: 99px; border: 1px solid <?= $bugIdor ? '#fca5a5' : '#bbf7d0' ?>;">
                  Trạng thái hệ thống hiện tại: <?= $bugIdor ? 'BUG_IDOR = 1 (Đang mở lỗi)' : 'BUG_IDOR = 0 (Đã vá an toàn)' ?>
                </span>
              </div>

              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div>
                  <label style="display: block; font-size: 12px; font-weight: 700; color: #1e293b; margin-bottom: 4px;">
                    Tên Lần Kiểm Thử (Run Name) <span style="color: #dc2626;">*</span>
                  </label>
                  <input
                    type="text"
                    name="run_name"
                    id="manualRunNameInput"
                    value="<?= htmlspecialchars('Lần kiểm thử #' . ($totalSavedRuns + 1) . ' - ' . ($bugIdor ? 'Khi bật lỗi IDOR' : 'Sau khi vá ABAC')) ?>"
                    placeholder="Nhập tên lần kiểm thử (VD: Kiểm thử trước khi vá lỗi...)"
                    style="width: 100%; padding: 8px 12px; border: 1px solid #93c5fd; border-radius: 8px; font-size: 13px; background: #fff;"
                  >
                </div>
                <div>
                  <label style="display: block; font-size: 12px; font-weight: 700; color: #1e293b; margin-bottom: 4px;">
                    Ghi Chú / Mục Đích Kiểm Thử (Tùy chọn)
                  </label>
                  <input
                    type="text"
                    name="run_note"
                    placeholder="VD: Kiểm tra bẫy R02 và phân quyền 5 phân hệ API..."
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff;"
                  >
                </div>
              </div>

              <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: space-between;">
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                  <button type="submit" onclick="document.getElementById('manualExecModeInput').value='run_all';" class="btn btn-primary" style="font-size: 12px; font-weight: 700; padding: 8px 16px;">
                    Thực Thi Toàn Bộ 24 Ca & Lưu Lần Kiểm Thử
                  </button>
                  <button type="submit" onclick="document.getElementById('manualExecModeInput').value='save_current';" class="btn btn-outline" style="font-size: 12px; font-weight: 700; background: #fff; border-color: #0284c7; color: #0369a1;">
                    Lưu Bảng Hiện Tại Thành 1 Lần Kiểm Thử
                  </button>
                  <a href="/?action=reset_manual_matrix" onclick="return confirmWithHold(event, { title: 'Đặt Lại Bảng Kiểm Thử', message: 'Bạn có chắc chắn muốn đặt lại toàn bộ 24 ca kiểm thử về trạng thái Chờ chạy (PENDING)?', badge: 'XÁC NHẬN ĐẶT LẠI' });" class="btn btn-outline" style="font-size: 12px; background: #fff;">
                    Đặt Lại Về Chờ Chạy (Reset)
                  </a>
                </div>
                <a href="/?view=tester&tab=compare" class="btn btn-outline" style="font-size: 12px; background: #fff; font-weight: 700; color: #4f46e5; border-color: #c7d2fe;">
                  So Sánh & Thống Kê (<?= $totalSavedRuns ?> lần chạy) &rarr;
                </a>
              </div>
            </form>

            <!-- THANH TRẠNG THÁI PHIÊN KIỂM THỬ HIỆN TẠI -->
            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 12.5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
              <div>
                <strong>Tiến độ bảng hiện tại:</strong>
                Đã chạy <strong id="matrixExecCount" style="color:#0369a1;"><?= $executedTestCount ?>/24</strong> ca |
                Đạt (PASS): <strong id="matrixPassCount" style="color:#15803d;"><?= $passedTestCount ?></strong> |
                Thất bại (FAIL): <strong id="matrixFailCount" style="color:#dc2626;"><?= $failedTestCount ?></strong> |
                Chờ chạy (PENDING): <strong id="matrixPendCount" style="color:#64748b;"><?= $pendingTestCount ?></strong>
                <?php if (!empty($_SESSION['last_manual_run_info'])): ?>
                  <span style="margin-left: 8px; color: #475569;">
                    (Phiên gần nhất: <strong><?= htmlspecialchars($_SESSION['last_manual_run_info']['name'] ?? '') ?></strong> - <?= htmlspecialchars($_SESSION['last_manual_run_info']['created_at'] ?? '') ?>)
                  </span>
                <?php endif; ?>
              </div>
              <div style="font-size: 11.5px; color: #64748b;">
                Mẹo: Bạn có thể nhấn <strong>"Chạy thử"</strong> ở từng dòng để kiểm tra lẻ từng ca hoặc nhấn <strong>"Thực Thi Toàn Bộ 24 Ca"</strong> ở trên.
              </div>
            </div>

            <div class="table-responsive">
              <div class="table-mobile-hint">Vuốt ngang để xem đủ ma trận 24 ca kiểm thử &raquo;</div>
              <table id="testCasesTable">
                <thead>
                  <tr>
                    <th>Mã Test</th>
                    <th>Phương Thức & API</th>
                    <th>Tài Khoản Thử Nghiệm</th>
                    <th>Vai Trò</th>
                    <th>Mô Tả Mục Tiêu Kiểm Thử</th>
                    <th>Kỳ Vọng</th>
                    <th>Kết Quả Thực Tế</th>
                    <th>Trạng Thái</th>
                    <th>Thao Tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($testCases as $tc): ?>
                    <?php
                      $st = $tc['status'] ?? 'PENDING';
                      $rowBg = '';
                      if ($st === 'FAIL') {
                          $rowBg = 'background: #fff1f2;';
                      } elseif ($tc['code'] === 'R02' && $st === 'PASS') {
                          $rowBg = 'background: #f0fdf4;';
                      }
                    ?>
                    <tr id="row-tc-<?= htmlspecialchars($tc['code']) ?>" style="<?= $rowBg ?>">
                      <td><strong style="color: #0369a1;"><?= htmlspecialchars($tc['code']) ?></strong></td>
                      <td><code><?= htmlspecialchars($tc['method']) ?> <?= htmlspecialchars($tc['api']) ?></code></td>
                      <td><?= htmlspecialchars($tc['user']) ?></td>
                      <td><span style="font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-weight: 700;"><?= htmlspecialchars($tc['role']) ?></span></td>
                      <td><?= htmlspecialchars($tc['desc']) ?></td>
                      <td><code style="color: #334155;"><?= htmlspecialchars($tc['expect']) ?></code></td>
                      <td id="actual-tc-<?= htmlspecialchars($tc['code']) ?>" style="font-size: 12px; color: <?= $st === 'PENDING' ? '#94a3b8' : ($st === 'FAIL' ? '#b91c1c' : '#0f172a') ?>; font-style: <?= $st === 'PENDING' ? 'italic' : 'normal' ?>;">
                        <?= htmlspecialchars($tc['actual']) ?>
                        <?php if (!empty($tc['latency_ms']) && $st !== 'PENDING'): ?>
                          <span style="font-size: 10px; color: #64748b; margin-left: 4px;">(<?= (int)$tc['latency_ms'] ?>ms)</span>
                        <?php endif; ?>
                      </td>
                      <td id="status-tc-<?= htmlspecialchars($tc['code']) ?>">
                        <?php if ($st === 'PASS'): ?>
                          <span style="font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px; background: #dcfce7; color: #15803d;">
                            PASS
                          </span>
                        <?php elseif ($st === 'FAIL'): ?>
                          <span style="font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px; background: #fee2e2; color: #b91c1c;">
                            FAIL
                          </span>
                        <?php else: ?>
                          <span style="font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px; background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">
                            CHỜ CHẠY
                          </span>
                        <?php endif; ?>
                      </td>
                      <td style="white-space: nowrap;">
                        <button
                          type="button"
                          onclick="runSingleManualCase('<?= htmlspecialchars($tc['code']) ?>', this)"
                          class="btn btn-outline"
                          style="font-size: 11px; padding: 4px 10px; font-weight: 700; color: #0284c7; border-color: #7dd3fc;"
                        >
                          Chạy thử
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div id="testCasesPagination" class="pagination-wrap"></div>
          </div>
