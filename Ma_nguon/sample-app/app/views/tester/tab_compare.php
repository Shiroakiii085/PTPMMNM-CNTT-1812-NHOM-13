          <?php
            $avgPassRate = 0.0;
            $cleanRunsCount = 0;
            $vulnRunsCount = 0;
            $avgDurationMs = 0;

            if ($totalSavedRuns > 0) {
                $sumRate = 0.0;
                $sumDur = 0;
                foreach ($testRunsHistory as $rItem) {
                    $sumRate += (float)($rItem['pass_rate'] ?? 0);
                    $sumDur += (int)($rItem['duration_ms'] ?? 0);
                    if ((int)($rItem['failed'] ?? 0) === 0 && (int)($rItem['passed'] ?? 0) === 24) {
                        $cleanRunsCount++;
                    }
                    if ((int)($rItem['failed'] ?? 0) > 0 || !empty($rItem['leak_count'])) {
                        $vulnRunsCount++;
                    }
                }
                $avgPassRate = round($sumRate / $totalSavedRuns, 1);
                $avgDurationMs = (int)round($sumDur / $totalSavedRuns);
            }

            // Xác định 2 lần kiểm thử để đối chiếu (Run A: Cơ sở / Trước, Run B: Đối chiếu / Sau)
            $selectedRunAId = trim((string)($_GET['run_a'] ?? ''));
            $selectedRunBId = trim((string)($_GET['run_b'] ?? ''));
            $diffFilter = trim((string)($_GET['diff_filter'] ?? 'all'));

            $runA = null;
            $runB = null;
            if ($totalSavedRuns > 0) {
                foreach ($testRunsHistory as $rItem) {
                    if (($rItem['id'] ?? '') === $selectedRunAId) $runA = $rItem;
                    if (($rItem['id'] ?? '') === $selectedRunBId) $runB = $rItem;
                }
                if (!$runB) {
                    $runB = $testRunsHistory[0];
                }
                if (!$runA) {
                    $runA = $testRunsHistory[1] ?? $testRunsHistory[0];
                }
            }
          ?>

          <!-- KHU VỰC 1: THỐNG KÊ TỔNG QUAN & BIỂU ĐỒ XU HƯỚNG CÁC LẦN KIỂM THỬ -->
          <div class="card">
            <div class="card-head">
              <div>
                <div class="card-title">Thống Kê Tổng Hợp & Biểu Đồ Lịch Sử Các Lần Kiểm Thử</div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                  Tổng hợp chỉ số chất lượng phân quyền qua các phiên kiểm thử thủ công được đặt tên.
                </div>
              </div>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="/?action=seed_sample_test_runs" onclick="return confirmWithHold(event, { title: 'Nạp 2 Lần Kiểm Thử Mẫu', message: 'Bạn có chắc chắn muốn khởi tạo 2 lần kiểm thử mẫu (Trước và Sau khi vá lỗi IDOR) vào lịch sử hệ thống?', badge: 'XÁC NHẬN KHỞI TẠO' });" class="btn btn-primary" style="font-size: 12px; background: #4f46e5;">
                  Nạp 2 Lần Chạy Mẫu (Trước & Sau Vá Lỗi IDOR)
                </a>
                <a href="/?view=tester&tab=matrix" class="btn btn-outline" style="font-size: 12px;">
                  Chạy Lần Kiểm Thử Mới
                </a>
                <?php if ($totalSavedRuns > 0): ?>
                  <a href="/?action=clear_test_runs_history" onclick="return confirmWithHold(event, { title: 'Xóa Lịch Sử Kiểm Thử', message: 'Bạn có chắc chắn muốn xóa toàn bộ các bản ghi lịch sử kiểm thử đã lưu?', badge: 'XÁC NHẬN XÓA' });" class="btn btn-outline" style="font-size: 12px; color: #b91c1c; border-color: #fca5a5;">
                    Xóa Tất Cả Lịch Sử
                  </a>
                <?php endif; ?>
              </div>
            </div>

            <!-- 4 THẺ CHỈ SỐ THỐNG KÊ CHUYÊN SÂU -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
              <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #0284c7; border-radius: 8px; padding: 14px;">
                <div style="font-size: 11.5px; color: #64748b; font-weight: 600;">Tổng Số Lần Kiểm Thử</div>
                <div style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 4px 0;"><?= $totalSavedRuns ?> lần</div>
                <div style="font-size: 11px; color: #64748b;">Lưu trữ bền vững theo tên đặt</div>
              </div>
              <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #16a34a; border-radius: 8px; padding: 14px;">
                <div style="font-size: 11.5px; color: #64748b; font-weight: 600;">Tỷ Lệ Đạt Trung Bình (Pass Rate)</div>
                <div style="font-size: 22px; font-weight: 800; color: #15803d; margin: 4px 0;"><?= $avgPassRate ?>%</div>
                <div style="font-size: 11px; color: #64748b;">Trung bình trên 24 ca / lần chạy</div>
              </div>
              <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #4f46e5; border-radius: 8px; padding: 14px;">
                <div style="font-size: 11.5px; color: #64748b; font-weight: 600;">Số Lần Đạt Chuẩn 100% (24/24)</div>
                <div style="font-size: 22px; font-weight: 800; color: #4338ca; margin: 4px 0;"><?= $cleanRunsCount ?> / <?= $totalSavedRuns ?> lần</div>
                <div style="font-size: 11px; color: #64748b;">Không có bất kỳ rò rỉ dữ liệu nào</div>
              </div>
              <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #ef4444; border-radius: 8px; padding: 14px;">
                <div style="font-size: 11.5px; color: #64748b; font-weight: 600;">Số Lần Phát Hiện Lỗ Hổng IDOR</div>
                <div style="font-size: 22px; font-weight: 800; color: #b91c1c; margin: 4px 0;"><?= $vulnRunsCount ?> lần</div>
                <div style="font-size: 11px; color: #64748b;">Thời gian chạy TB: <?= $avgDurationMs ?> ms</div>
              </div>
            </div>

            <?php if ($totalSavedRuns === 0): ?>
              <div style="text-align: center; padding: 36px 20px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px;">
                <div style="font-size: 15px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                  Chưa có lần kiểm thử nào được lưu trong lịch sử
                </div>
                <p style="font-size: 13px; color: #64748b; max-width: 560px; margin: 0 auto 16px;">
                  Hãy sang tab <strong>Kiểm Thử Phân Quyền Thủ Công (24 Cases)</strong> để đặt tên và chạy kiểm thử, hoặc nhấn nút bên dưới để tạo nhanh 2 lần chạy mẫu (Trước & Sau khi vá lỗi IDOR) phục vụ đối chiếu ngay lập tức.
                </p>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                  <a href="/?action=seed_sample_test_runs" onclick="return confirmWithHold(event, { title: 'Nạp 2 Lần Kiểm Thử Mẫu', message: 'Bạn có chắc chắn muốn khởi tạo 2 lần kiểm thử mẫu (Trước và Sau khi vá lỗi IDOR) vào lịch sử hệ thống?', badge: 'XÁC NHẬN KHỞI TẠO' });" class="btn btn-primary" style="background: #4f46e5;">
                    Tạo Nhanh 2 Lần Kiểm Thử Mẫu Để So Sánh
                  </a>
                  <a href="/?view=tester&tab=matrix" class="btn btn-outline">
                    Đi Tới Bảng Kiểm Thử Thủ Công 24 Ca
                  </a>
                </div>
              </div>
            <?php else: ?>
              <!-- BIỂU ĐỒ THANH THỐNG KÊ KẾT QUẢ QUA CÁC LẦN CHẠY -->
              <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
                <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">
                  Biểu Đồ Tỷ Lệ PASS / FAIL Qua Các Lần Kiểm Thử (Theo Tên Đã Đặt)
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                  <?php foreach (array_slice($testRunsHistory, 0, 10) as $idx => $rChart): ?>
                    <?php
                      $tot = max(1, (int)($rChart['total'] ?? 24));
                      $pPct = round(((int)($rChart['passed'] ?? 0) / $tot) * 100, 1);
                      $fPct = round(((int)($rChart['failed'] ?? 0) / $tot) * 100, 1);
                      $wPct = max(0, 100 - $pPct - $fPct);
                    ?>
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px;">
                      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 6px; font-size: 12px;">
                        <div>
                          <span style="font-weight: 800; color: #0369a1;">#<?= $totalSavedRuns - $idx ?>:</span>
                          <strong style="color: #0f172a; font-size: 13px;"><?= htmlspecialchars($rChart['name'] ?? '') ?></strong>
                          <span style="color: #64748b; font-size: 11px; margin-left: 6px;">(<?= htmlspecialchars($rChart['created_at'] ?? '') ?>)</span>
                          <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-left: 6px; background: <?= !empty($rChart['bug_idor']) ? '#fee2e2' : '#dcfce7' ?>; color: <?= !empty($rChart['bug_idor']) ? '#b91c1c' : '#15803d' ?>;">
                            <?= !empty($rChart['bug_idor']) ? 'BUG_IDOR=1 (Mở lỗi)' : 'BUG_IDOR=0 (An toàn)' ?>
                          </span>
                        </div>
                        <div style="font-weight: 700;">
                          <span style="color: #15803d;"><?= (int)($rChart['passed'] ?? 0) ?> PASS</span> /
                          <span style="color: #dc2626;"><?= (int)($rChart['failed'] ?? 0) ?> FAIL</span>
                          <?php if (!empty($rChart['pending'])): ?>
                            / <span style="color: #64748b;"><?= (int)$rChart['pending'] ?> CHỜ</span>
                          <?php endif; ?>
                          <span style="margin-left: 6px; background: #eff6ff; color: #1d4ed8; padding: 2px 8px; border-radius: 99px; font-size: 11px;">
                            <?= $pPct ?>%
                          </span>
                        </div>
                      </div>
                      <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 99px; overflow: hidden; display: flex;">
                        <?php if ($pPct > 0): ?>
                          <div style="width: <?= $pPct ?>%; background: #22c55e; height: 100%;" title="PASS: <?= $pPct ?>%"></div>
                        <?php endif; ?>
                        <?php if ($fPct > 0): ?>
                          <div style="width: <?= $fPct ?>%; background: #ef4444; height: 100%;" title="FAIL: <?= $fPct ?>%"></div>
                        <?php endif; ?>
                        <?php if ($wPct > 0): ?>
                          <div style="width: <?= $wPct ?>%; background: #94a3b8; height: 100%;" title="PENDING: <?= $wPct ?>%"></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($totalSavedRuns > 0 && $runA && $runB): ?>
            <?php
              // Xây dựng map 24 ca của Run A và Run B để đối chiếu từng ca
              $mapA = [];
              foreach (($runA['cases'] ?? []) as $ca) {
                  $mapA[$ca['code']] = $ca;
              }
              $mapB = [];
              foreach (($runB['cases'] ?? []) as $cb) {
                  $mapB[$cb['code']] = $cb;
              }

              $allCodes = array_keys($mapB ?: $mapA);
              $fixedList = [];
              $regressedList = [];
              $changedList = [];
              $unchangedCount = 0;
              $comparisonRows = [];

              foreach ($allCodes as $cCode) {
                  $itemA = $mapA[$cCode] ?? ['code' => $cCode, 'status' => 'PENDING', 'actual' => 'Chưa chạy'];
                  $itemB = $mapB[$cCode] ?? ['code' => $cCode, 'status' => 'PENDING', 'actual' => 'Chưa chạy'];
                  $stA = $itemA['status'] ?? 'PENDING';
                  $stB = $itemB['status'] ?? 'PENDING';

                  $verdictType = 'unchanged';
                  $verdictLabel = 'Giữ nguyên (' . $stB . ')';
                  $verdictBg = '#f1f5f9';
                  $verdictColor = '#475569';
                  $rowHighlight = '';

                  if ($stA !== $stB) {
                      $changedList[] = $cCode;
                      if ($stA === 'FAIL' && $stB === 'PASS') {
                          $fixedList[] = $cCode;
                          $verdictType = 'fixed';
                          $verdictLabel = 'ĐÃ KHẮC PHỤC (FAIL -> PASS)';
                          $verdictBg = '#dcfce7';
                          $verdictColor = '#15803d';
                          $rowHighlight = 'background: #f0fdf4;';
                      } elseif ($stA === 'PASS' && $stB === 'FAIL') {
                          $regressedList[] = $cCode;
                          $verdictType = 'regressed';
                          $verdictLabel = 'PHÁT SINH LỖI (PASS -> FAIL)';
                          $verdictBg = '#fee2e2';
                          $verdictColor = '#b91c1c';
                          $rowHighlight = 'background: #fff1f2;';
                      } else {
                          $verdictType = 'changed';
                          $verdictLabel = 'THAY ĐỔI (' . $stA . ' -> ' . $stB . ')';
                          $verdictBg = '#e0f2fe';
                          $verdictColor = '#0369a1';
                          $rowHighlight = 'background: #f8fafc;';
                      }
                  } else {
                      $unchangedCount++;
                      if ($stB === 'FAIL') {
                          $verdictLabel = 'Vẫn còn lỗi (FAIL -> FAIL)';
                          $verdictBg = '#fef3c7';
                          $verdictColor = '#b45309';
                      }
                  }

                  if ($diffFilter === 'diff_only' && $stA === $stB) {
                      continue;
                  }

                  $comparisonRows[] = [
                      'code' => $cCode,
                      'meta' => $itemB,
                      'a' => $itemA,
                      'b' => $itemB,
                      'verdict_type' => $verdictType,
                      'verdict_label' => $verdictLabel,
                      'verdict_bg' => $verdictBg,
                      'verdict_color' => $verdictColor,
                      'row_style' => $rowHighlight,
                  ];
              }

              $rateDelta = round(((float)($runB['pass_rate'] ?? 0)) - ((float)($runA['pass_rate'] ?? 0)), 1);
            ?>

            <!-- KHU VỰC 2: CÔNG CỤ SO SÁNH ĐỐI CHIẾU GIỮA 2 LẦN KIỂM THỬ (RUN A VS RUN B) -->
            <div class="card">
              <div class="card-head">
                <div>
                  <div class="card-title">So Sánh Đối Chiếu Chi Tiết Giữa 2 Lần Kiểm Thử (Run-to-Run Diff)</div>
                  <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                    Chọn 2 lần kiểm thử bất kỳ theo tên đã đặt để phân tích các ca đã khắc phục (FAIL &rarr; PASS) hoặc hồi quy lỗi (PASS &rarr; FAIL).
                  </div>
                </div>
              </div>

              <!-- BỘ CHỌN 2 LẦN KIỂM THỬ -->
              <form method="GET" action="/" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                <input type="hidden" name="view" value="tester">
                <input type="hidden" name="tab" value="compare">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; align-items: end;">
                  <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                      Lần Kiểm Thử Cơ Sở (Lần A - Trước):
                    </label>
                    <select name="run_a" style="width: 100%; padding: 8px 10px; border: 1px solid #94a3b8; border-radius: 8px; font-size: 12.5px; background: #fff;">
                      <?php foreach ($testRunsHistory as $rOpt): ?>
                        <option value="<?= htmlspecialchars($rOpt['id']) ?>" <?= ($runA['id'] ?? '') === ($rOpt['id'] ?? '') ? 'selected' : '' ?>>
                          <?= htmlspecialchars($rOpt['name']) ?> (<?= (int)$rOpt['passed'] ?>/24 PASS - <?= htmlspecialchars($rOpt['created_at']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                      Lần Kiểm Thử Đối Chiếu (Lần B - Sau):
                    </label>
                    <select name="run_b" style="width: 100%; padding: 8px 10px; border: 1px solid #94a3b8; border-radius: 8px; font-size: 12.5px; background: #fff;">
                      <?php foreach ($testRunsHistory as $rOpt): ?>
                        <option value="<?= htmlspecialchars($rOpt['id']) ?>" <?= ($runB['id'] ?? '') === ($rOpt['id'] ?? '') ? 'selected' : '' ?>>
                          <?= htmlspecialchars($rOpt['name']) ?> (<?= (int)$rOpt['passed'] ?>/24 PASS - <?= htmlspecialchars($rOpt['created_at']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                      Chế Độ Hiển Thị Bảng Đối Chiếu:
                    </label>
                    <select name="diff_filter" style="width: 100%; padding: 8px 10px; border: 1px solid #94a3b8; border-radius: 8px; font-size: 12.5px; background: #fff;">
                      <option value="all" <?= $diffFilter === 'all' ? 'selected' : '' ?>>Hiển thị toàn bộ 24 ca kiểm thử</option>
                      <option value="diff_only" <?= $diffFilter === 'diff_only' ? 'selected' : '' ?>>Chỉ hiển thị các ca có thay đổi (Diff Only)</option>
                    </select>
                  </div>

                  <div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 9px 14px; font-size: 12.5px; font-weight: 700;">
                      Đối Chiếu Hai Lần Chạy
                    </button>
                  </div>
                </div>
              </form>

              <!-- TÓM TẮT KẾT QUẢ ĐỐI CHIẾU GIỮA LẦN A VÀ LẦN B -->
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 16px;">
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px;">
                  <div style="font-size: 11px; color: #166534; font-weight: 700;">ĐÃ KHẮC PHỤC LỖI (FAIL &rarr; PASS)</div>
                  <div style="font-size: 20px; font-weight: 800; color: #15803d; margin-top: 4px;">
                    <?= count($fixedList) ?> ca <?= !empty($fixedList) ? '(' . implode(', ', $fixedList) . ')' : '' ?>
                  </div>
                </div>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px;">
                  <div style="font-size: 11px; color: #991b1b; font-weight: 700;">PHÁT SINH LỖI MỚI (PASS &rarr; FAIL)</div>
                  <div style="font-size: 20px; font-weight: 800; color: #b91c1c; margin-top: 4px;">
                    <?= count($regressedList) ?> ca <?= !empty($regressedList) ? '(' . implode(', ', $regressedList) . ')' : '' ?>
                  </div>
                </div>
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px;">
                  <div style="font-size: 11px; color: #1e40af; font-weight: 700;">CHÊNH LỆCH TỶ LỆ ĐẠT (PASS RATE)</div>
                  <div style="font-size: 20px; font-weight: 800; color: <?= $rateDelta >= 0 ? '#1d4ed8' : '#dc2626' ?>; margin-top: 4px;">
                    <?= ($rateDelta > 0 ? '+' : '') . $rateDelta ?>% (<?= $runA['pass_rate'] ?>% &rarr; <?= $runB['pass_rate'] ?>%)
                  </div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                  <div style="font-size: 11px; color: #475569; font-weight: 700;">GIỮ NGUYÊN KẾT QUẢ (UNCHANGED)</div>
                  <div style="font-size: 20px; font-weight: 800; color: #334155; margin-top: 4px;">
                    <?= $unchangedCount ?> / 24 ca
                  </div>
                </div>
              </div>

              <!-- BẢNG SO SÁNH SIDE-BY-SIDE 24 CA -->
              <div class="table-responsive">
                <table>
                  <thead>
                    <tr>
                      <th>Mã Ca</th>
                      <th>API & Tài Khoản</th>
                      <th>Kỳ Vọng</th>
                      <th style="background: #f1f5f9;">Lần A: <?= htmlspecialchars($runA['name']) ?></th>
                      <th style="background: #eff6ff;">Lần B: <?= htmlspecialchars($runB['name']) ?></th>
                      <th>Đánh Giá Thay Đổi (Diff Verdict)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($comparisonRows)): ?>
                      <tr>
                        <td colspan="6" style="text-align: center; padding: 28px; color: #64748b;">
                          Không có sự khác biệt nào giữa 2 lần kiểm thử đã chọn (Tất cả 24 ca đều giữ nguyên trạng thái).
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($comparisonRows as $row): ?>
                        <?php
                          $stA = $row['a']['status'] ?? 'PENDING';
                          $stB = $row['b']['status'] ?? 'PENDING';
                        ?>
                        <tr style="<?= $row['row_style'] ?>">
                          <td><strong style="color: #0369a1;"><?= htmlspecialchars($row['code']) ?></strong></td>
                          <td>
                            <code><?= htmlspecialchars(($row['meta']['method'] ?? 'GET') . ' ' . ($row['meta']['api'] ?? '')) ?></code><br>
                            <span style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($row['meta']['user'] ?? '') ?> (<code><?= htmlspecialchars($row['meta']['role'] ?? '') ?></code>)</span>
                          </td>
                          <td><code><?= htmlspecialchars($row['meta']['expect'] ?? '') ?></code></td>
                          <td>
                            <span style="font-weight: 800; font-size: 10.5px; padding: 2px 7px; border-radius: 5px; background: <?= $stA === 'PASS' ? '#dcfce7' : ($stA === 'FAIL' ? '#fee2e2' : '#f1f5f9') ?>; color: <?= $stA === 'PASS' ? '#15803d' : ($stA === 'FAIL' ? '#b91c1c' : '#64748b') ?>;">
                              <?= htmlspecialchars($stA) ?>
                            </span>
                            <div style="font-size: 11px; color: #475569; margin-top: 3px;"><?= htmlspecialchars($row['a']['actual'] ?? '') ?></div>
                          </td>
                          <td>
                            <span style="font-weight: 800; font-size: 10.5px; padding: 2px 7px; border-radius: 5px; background: <?= $stB === 'PASS' ? '#dcfce7' : ($stB === 'FAIL' ? '#fee2e2' : '#f1f5f9') ?>; color: <?= $stB === 'PASS' ? '#15803d' : ($stB === 'FAIL' ? '#b91c1c' : '#64748b') ?>;">
                              <?= htmlspecialchars($stB) ?>
                            </span>
                            <div style="font-size: 11px; color: #475569; margin-top: 3px;"><?= htmlspecialchars($row['b']['actual'] ?? '') ?></div>
                          </td>
                          <td>
                            <span style="font-weight: 800; font-size: 11px; padding: 4px 10px; border-radius: 6px; display: inline-block; background: <?= $row['verdict_bg'] ?>; color: <?= $row['verdict_color'] ?>;">
                              <?= htmlspecialchars($row['verdict_label']) ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- KHU VỰC 3: QUẢN LÝ DANH SÁCH LỊCH SỬ CÁC LẦN KIỂM THỬ & ĐỔI TÊN -->
            <div class="card">
              <div class="card-head">
                <div>
                  <div class="card-title">Danh Sách Lịch Sử Các Lần Kiểm Thử & Đặt Lại Tên</div>
                  <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                    Quản lý các bản ghi kiểm thử đã lưu, cho phép đổi tên lần kiểm thử, nạp lại vào bảng Ma trận hoặc xóa.
                  </div>
                </div>
              </div>

              <div class="table-responsive">
                <table>
                  <thead>
                    <tr>
                      <th>Mã Run & Thời Gian</th>
                      <th>Tên Lần Kiểm Thử & Ghi Chú</th>
                      <th>Người Chạy</th>
                      <th>Trạng Thái BUG_IDOR</th>
                      <th>Kết Quả Thống Kê</th>
                      <th>Thao Tác</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($testRunsHistory as $rHist): ?>
                      <?php $rId = $rHist['id'] ?? ''; ?>
                      <tr>
                        <td style="white-space: nowrap;">
                          <code style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($rId) ?></code><br>
                          <span style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($rHist['created_at'] ?? '') ?></span>
                        </td>
                        <td style="min-width: 260px;">
                          <div style="font-weight: 800; color: #0369a1; font-size: 13.5px;"><?= htmlspecialchars($rHist['name'] ?? '') ?></div>
                          <?php if (!empty($rHist['note'])): ?>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;"><?= htmlspecialchars($rHist['note']) ?></div>
                          <?php endif; ?>
                          <!-- Form đổi tên nhanh ẩn/hiện -->
                          <form id="rename-form-<?= htmlspecialchars($rId) ?>" method="POST" action="/?action=rename_test_run" onsubmit="return confirmFormWithHold(event, this, { title: 'Xác Nhận Lưu Tên Mới', message: 'Bạn có chắc chắn muốn lưu tên và ghi chú mới cho lần kiểm thử này?', badge: 'XÁC NHẬN LƯU TÊN' });" style="display: none; margin-top: 8px; background: #f8fafc; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                            <input type="hidden" name="run_id" value="<?= htmlspecialchars($rId) ?>">
                            <input type="text" name="new_name" value="<?= htmlspecialchars($rHist['name'] ?? '') ?>" placeholder="Tên lần kiểm thử mới..." style="width: 100%; padding: 5px 8px; font-size: 12px; border: 1px solid #94a3b8; border-radius: 4px; margin-bottom: 6px;" required>
                            <input type="text" name="new_note" value="<?= htmlspecialchars($rHist['note'] ?? '') ?>" placeholder="Ghi chú..." style="width: 100%; padding: 5px 8px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 4px; margin-bottom: 6px;">
                            <div style="display: flex; gap: 6px;">
                              <button type="submit" class="btn btn-primary" style="font-size: 11px; padding: 4px 10px;">Lưu tên mới</button>
                              <button type="button" onclick="toggleRenameRunForm('<?= htmlspecialchars($rId) ?>')" class="btn btn-outline" style="font-size: 11px; padding: 4px 10px;">Hủy</button>
                            </div>
                          </form>
                        </td>
                        <td>
                          <strong><?= htmlspecialchars($rHist['tester_name'] ?? 'Kỹ sư Kiểm thử') ?></strong><br>
                          <code style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($rHist['tester'] ?? 'tester_sec') ?></code>
                        </td>
                        <td>
                          <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: <?= !empty($rHist['bug_idor']) ? '#fee2e2' : '#dcfce7' ?>; color: <?= !empty($rHist['bug_idor']) ? '#b91c1c' : '#15803d' ?>;">
                            <?= !empty($rHist['bug_idor']) ? 'BUG_IDOR = 1 (Bật)' : 'BUG_IDOR = 0 (Tắt)' ?>
                          </span>
                        </td>
                        <td>
                          <span style="color: #15803d; font-weight: 800;"><?= (int)($rHist['passed'] ?? 0) ?> PASS</span> /
                          <span style="color: #dc2626; font-weight: 800;"><?= (int)($rHist['failed'] ?? 0) ?> FAIL</span>
                          <?php if (!empty($rHist['pending'])): ?>
                            / <span style="color: #64748b;"><?= (int)$rHist['pending'] ?> CHỜ</span>
                          <?php endif; ?>
                          <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                            Đạt: <strong><?= (float)($rHist['pass_rate'] ?? 0) ?>%</strong> | Thời gian: <?= (int)($rHist['duration_ms'] ?? 0) ?>ms
                          </div>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <button type="button" onclick="toggleRenameRunForm('<?= htmlspecialchars($rId) ?>')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px;">
                              Đổi tên
                            </button>
                            <a href="/?action=load_run_to_matrix&run_id=<?= urlencode($rId) ?>" onclick="return confirmWithHold(event, { title: 'Nạp Kết Quả Lên Ma Trận', message: 'Bạn có chắc chắn muốn nạp kết quả của lần kiểm thử này sang bảng Ma trận 24 ca kiểm thử?', badge: 'XÁC NHẬN NẠP' });" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; color: #0369a1;">
                              Xem trên Ma trận
                            </a>
                            <a href="/?action=delete_test_run&run_id=<?= urlencode($rId) ?>" onclick="return confirmWithHold(event, { title: 'Xóa Lần Kiểm Thử', message: 'Bạn có chắc chắn muốn xóa vĩnh viễn lần kiểm thử này khỏi lịch sử?', badge: 'XÁC NHẬN XÓA' });" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; color: #b91c1c; border-color: #fca5a5;">
                              Xóa
                            </a>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>
