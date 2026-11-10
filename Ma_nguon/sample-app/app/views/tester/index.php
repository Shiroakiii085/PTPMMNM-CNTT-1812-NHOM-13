        <?php $testerTab = $_GET['tab'] ?? 'incidents'; ?>

        <!-- HEADER BẢNG ĐIỀU KHIỂN KIỂM THỬ -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
          <div>
            <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; margin: 0;">
              <span style="background: #0284c7; color: #fff; width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800;">SEC</span>
              TRUNG TÂM GIÁM SÁT AN NINH & KIỂM THỬ PHÂN QUYỀN
            </h1>
            <p style="color: var(--muted); font-size: 13px; margin-top: 4px;">
              Giao diện kiểm thử và cảnh báo vi phạm dành cho <strong><?= htmlspecialchars($currentUser['full_name'] ?? 'Kỹ sư Bảo mật') ?></strong> (<code><?= htmlspecialchars($currentUser['username'] ?? 'tester_sec') ?></code> - Vai trò: <code><?= htmlspecialchars($currentUser['role'] ?? 'auditor') ?></code>).
            </p>
          </div>
          <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="/?action=simulate_security_alert" onclick="return confirmWithHold(event, { title: 'Mô Phỏng Tấn Công IDOR', message: 'Kích hoạt một kịch bản tấn công truy cập chéo hồ sơ mẫu để kiểm tra cơ chế phát hiện và cảnh báo của hệ thống.' })" class="btn btn-outline" style="border-color: #fca5a5; color: #b91c1c; font-size: 12px;" title="Tạo sự cố tấn công mẫu để kiểm thử cơ chế phát hiện">
              Mô Phỏng Tấn Công IDOR
            </a>
            <a href="/?action=clear_security_alerts" onclick="return confirmWithHold(event, { title: 'Xác Nhận Xoá Nhật Ký Sự Cố', message: 'Bạn có chắc chắn muốn xóa sạch toàn bộ nhật ký sự cố vi phạm bảo mật thời gian thực?' });" class="btn btn-outline" style="font-size: 12px;">
              Xóa Nhật Ký
            </a>
          </div>
        </div>

        <!-- KPI METRIC CARDS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
          <div class="card" style="border-left: 4px solid #ef4444; margin: 0; padding: 16px;">
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">Cảnh Báo Sự Cố Xâm Phạm</div>
            <div style="font-size: 24px; font-weight: 800; color: #b91c1c; margin: 6px 0 2px;">
              <?= $incidentCount ?>
              <span style="font-size: 12px; font-weight: 600; color: #64748b;">(<?= $criticalCount ?> Nghiêm trọng)</span>
            </div>
            <div style="font-size: 11px; color: #94a3b8;">Thời gian thực (Live Feeds)</div>
          </div>

          <div class="card" style="border-left: 4px solid <?= $bugIdor ? '#ef4444' : '#16a34a' ?>; margin: 0; padding: 16px;">
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">Trạng Thái Lỗ Hổng IDOR (CWE-639)</div>
            <div style="font-size: 20px; font-weight: 800; color: <?= $bugIdor ? '#dc2626' : '#16a34a' ?>; margin: 6px 0 2px;">
              <?= $bugIdor ? 'LỖ HỔNG ĐANG MỞ' : 'HỆ THỐNG AN TOÀN' ?>
            </div>
            <div style="font-size: 11px; color: #64748b;">
              <?= $bugIdor ? 'Bệnh nhân An có thể đọc trộm bệnh án Bình' : 'Cơ chế ABAC đang bảo vệ 100%' ?>
            </div>
          </div>

          <div class="card" style="border-left: 4px solid <?= $executedTestCount === 0 ? '#64748b' : ($failedTestCount > 0 ? '#d97706' : '#16a34a') ?>; margin: 0; padding: 16px;">
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">Kiểm Thử Thủ Công 24 Ca (Hiện Tại)</div>
            <div id="kpiManualMainVal" style="font-size: 22px; font-weight: 800; color: <?= $executedTestCount === 0 ? '#475569' : ($failedTestCount > 0 ? '#b45309' : '#15803d') ?>; margin: 6px 0 2px;">
              <?php if ($executedTestCount === 0): ?>
                CHỜ CHẠY (0/24)
              <?php else: ?>
                <?= $passedTestCount ?> PASS / <?= $failedTestCount ?> FAIL
              <?php endif; ?>
            </div>
            <div id="kpiManualSubVal" style="font-size: 11px; color: #64748b;">
              <?php if ($executedTestCount === 0): ?>
                Chế độ thủ công — Nhấn nút chạy để kiểm tra
              <?php elseif ($pendingTestCount > 0): ?>
                Đã chạy <?= $executedTestCount ?>/24 ca (Còn <?= $pendingTestCount ?> ca chờ)
              <?php elseif ($failedTestCount > 0): ?>
                <span style="color:#dc2626;font-weight:700;"><?= $failedTestCount ?> FAIL (Phát hiện rò rỉ IDOR R02)</span>
              <?php else: ?>
                Hoàn tất 24/24 ca đạt chuẩn an toàn
              <?php endif; ?>
            </div>
          </div>

          <div class="card" style="border-left: 4px solid #0284c7; margin: 0; padding: 16px;">
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">Lịch Sử & So Sánh Kiểm Thử</div>
            <div style="font-size: 20px; font-weight: 800; color: #0369a1; margin: 6px 0 2px;">
              <?= $totalSavedRuns ?> LẦN CHẠY
            </div>
            <div style="font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <?= $totalSavedRuns > 0 ? ('Mới nhất: ' . htmlspecialchars($testRunsHistory[0]['name'] ?? '')) : 'Hỗ trợ đặt tên & đối chiếu kết quả' ?>
            </div>
          </div>
        </div>

        <!-- THÔNG BÁO FLASH CHO TRUNG TÂM KIỂM THỬ -->
        <?php if (!empty($_GET['msg'])): ?>
          <?php
            $tMsg = (string)$_GET['msg'];
            $flashMap = [
                'manual_run_saved' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'color' => '#065f46', 'text' => 'Đã thực thi thủ công 24 ca kiểm thử và lưu kết quả thành công! Bạn có thể chuyển sang trang "So Sánh & Thống Kê Các Lần Kiểm Thử" để đối chiếu.'],
                'single_case_ran'  => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'color' => '#1e40af', 'text' => 'Đã thực thi thủ công ca kiểm thử ' . htmlspecialchars($_GET['case'] ?? '') . '. Bạn có thể đặt tên và nhấn "Lưu Bảng Hiện Tại" để ghi vào lịch sử so sánh.'],
                'matrix_reset'     => ['bg' => '#f8fafc', 'border' => '#cbd5e1', 'color' => '#334155', 'text' => 'Đã đặt lại toàn bộ 24 ca kiểm thử về trạng thái Chờ chạy (PENDING).'],
                'run_loaded'       => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'color' => '#1e40af', 'text' => 'Đã nạp kết quả lần kiểm thử từ lịch sử lên bảng Ma trận 24 ca.'],
                'sample_seeded'    => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'color' => '#065f46', 'text' => 'Đã khởi tạo 2 lần kiểm thử mẫu (Trước khi vá lỗi IDOR & Sau khi vá lỗi ABAC) và tự động nạp vào bảng đối chiếu bên dưới!'],
                'run_renamed'      => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'color' => '#065f46', 'text' => 'Đã cập nhật tên và ghi chú của lần kiểm thử thành công!'],
                'run_deleted'      => ['bg' => '#fef2f2', 'border' => '#fecaca', 'color' => '#991b1b', 'text' => 'Đã xóa bản ghi lần kiểm thử khỏi lịch sử.'],
                'runs_cleared'     => ['bg' => '#fef2f2', 'border' => '#fecaca', 'color' => '#991b1b', 'text' => 'Đã xóa sạch toàn bộ lịch sử các lần kiểm thử.'],
            ];
            $fl = $flashMap[$tMsg] ?? null;
          ?>
          <?php if ($fl): ?>
            <div style="background: <?= $fl['bg'] ?>; border: 1px solid <?= $fl['border'] ?>; color: <?= $fl['color'] ?>; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
              <span><strong><?= $fl['text'] ?></strong></span>
              <?php if ($tMsg === 'manual_run_saved'): ?>
                <a href="/?view=tester&tab=compare" class="btn btn-primary" style="font-size: 11px; padding: 5px 12px;">Mở Trang So Sánh & Thống Kê &rarr;</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- TABS CHỨC NĂNG KIỂM THỬ -->
        <div class="nav-tabs-responsive">
          <a href="/?view=tester&tab=incidents" class="btn <?= $testerTab === 'incidents' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
            Nhật Ký Cảnh Báo Xâm Phạm Thời Gian Thực (<?= $incidentCount ?>)
          </a>
          <a href="/?view=tester&tab=matrix" class="btn <?= $testerTab === 'matrix' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
            Kiểm Thử Phân Quyền Thủ Công (24 Cases)
          </a>
          <a href="/?view=tester&tab=compare" class="btn <?= $testerTab === 'compare' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
            So Sánh & Thống Kê Các Lần Kiểm Thử (<?= $totalSavedRuns ?>)
          </a>
          <a href="/?view=tester&tab=full_tests" class="btn <?= $testerTab === 'full_tests' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
            Chạy Toàn Bộ Test Suite (88 Tests CLI)
          </a>
        </div>


        <?php
          if ($testerTab === 'incidents') {
              require __DIR__ . '/tab_incidents.php';
          } elseif ($testerTab === 'matrix') {
              require __DIR__ . '/tab_matrix.php';
          } elseif ($testerTab === 'compare') {
              require __DIR__ . '/tab_compare.php';
          } elseif ($testerTab === 'full_tests') {
              require __DIR__ . '/tab_full_tests.php';
          }
        ?>
