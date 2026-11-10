          <div class="card">
            <div class="card-head">
              <div>
                <div class="card-title">Dòng Sự Kiện An Ninh & Cảnh Báo Xâm Phạm Thời Gian Thực</div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                  Tự động ghi nhận khi bất kỳ người dùng, bệnh nhân hoặc nhân viên nào có thao tác vi phạm hoặc cố tình lợi dụng lỗ hổng phân quyền.
                </div>
              </div>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="/?action=export_incidents_csv" onclick="return confirmWithHold(event, { title: 'Tải Về Báo Cáo CSV', message: 'Bạn có chắc chắn muốn tải về toàn bộ danh sách cảnh báo vi phạm an ninh dưới dạng tệp CSV?' })" class="btn btn-outline" style="font-size: 12px; color: #b45309; border-color: #fcd34d; font-weight: 600;" title="Tải toàn bộ nhật ký vi phạm an ninh ra file Excel CSV">
                  Xuất CSV Sự Cố
                </a>
              </div>
            </div>

            <?php if (empty($allIncidents)): ?>
              <div style="text-align: center; padding: 40px; color: var(--muted); background: #f8fafc; border-radius: 8px;">
                Chưa có sự cố an ninh nào được ghi nhận. Hệ thống đang an toàn tuyệt đối.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <div class="table-mobile-hint">Vuốt ngang để xem chi tiết mã lỗi & URI &raquo;</div>
                <table id="incidentsTable">
                  <thead>
                    <tr>
                      <th>Mã Sự Cố & Thời Gian</th>
                      <th>Mức Độ</th>
                      <th>Đối Tượng Vi Phạm (Actor)</th>
                      <th>Mục Tiêu (Target)</th>
                      <th>Chi Tiết Hành Vi Vi Phạm & Phân Loại</th>
                      <th>Mã Phản Hồi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($allIncidents as $inc): ?>
                      <tr>
                        <td style="white-space: nowrap;">
                          <code style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($inc['id'] ?? '') ?></code><br>
                          <span style="font-size: 11px; color: var(--muted);"><?= htmlspecialchars($inc['time'] ?? '') ?></span>
                        </td>
                        <td>
                          <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px; background: <?= ($inc['severity'] ?? '') === 'CRITICAL' ? '#fee2e2' : '#fef3c7' ?>; color: <?= ($inc['severity'] ?? '') === 'CRITICAL' ? '#b91c1c' : '#b45309' ?>;">
                            <?= htmlspecialchars($inc['severity'] ?? 'INFO') ?>
                          </span>
                        </td>
                        <td>
                          <strong><?= htmlspecialchars($inc['actor_name'] ?? 'Khách vãng lai') ?></strong><br>
                          <span style="font-size: 11px; color: #64748b;">
                            <code><?= htmlspecialchars($inc['actor_username'] ?? 'anon') ?></code> (<?= htmlspecialchars($inc['actor_role'] ?? 'anon') ?>)
                          </span>
                        </td>
                        <td>
                          <span style="font-size: 12px; font-weight: 600; color: #0284c7;">
                            <?= htmlspecialchars($inc['target'] ?? '-') ?>
                          </span>
                        </td>
                        <td>
                          <div style="font-size: 13px; color: #0f172a;">
                            <?= htmlspecialchars($inc['description'] ?? '') ?>
                          </div>
                          <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                            Phân loại: <strong><?= htmlspecialchars($inc['cwe'] ?? 'CWE-639') ?></strong>
                            <?php if (!empty($inc['uri'])): ?>
                              | URI: <code><?= htmlspecialchars($inc['uri']) ?></code>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td>
                          <?php $sc = $inc['status_code'] ?? 200; ?>
                          <span style="font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px; background: <?= $sc === 200 ? ($inc['severity'] === 'CRITICAL' ? '#fee2e2' : '#dcfce7') : '#fee2e2' ?>; color: <?= $sc === 200 ? ($inc['severity'] === 'CRITICAL' ? '#b91c1c' : '#15803d') : '#b91c1c' ?>;">
                            HTTP <?= $sc ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div id="incidentsPagination" class="pagination-wrap"></div>
            <?php endif; ?>
          </div>
