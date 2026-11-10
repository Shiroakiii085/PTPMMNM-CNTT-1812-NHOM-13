        <?php
          $isMember = ($currentUser && $currentUser['role'] === 'member');
          $displayRecords = [];
          if ($currentUser) {
              if ($isMember) {
                  $displayRecords = array_values(array_filter($mockRecords, fn($r) => checkRecordOwnerMatch($r['owner_username'], $currentUser['username'])));
              } else {
                  $displayRecords = array_values($mockRecords);
              }
          }
        ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'record_deleted'): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            <strong>✓ Đã xóa thành công:</strong> Hồ sơ bệnh án đã được xóa hoàn toàn khỏi cơ sở dữ liệu hệ thống.
          </div>
        <?php elseif (isset($_GET['not_found'])): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div>
              <strong style="font-size: 14px;">THÔNG BÁO TRA CỨU:</strong> ID bệnh án <?= (isset($_GET['searched_id']) && $_GET['searched_id'] !== '') ? '<strong>#' . htmlspecialchars($_GET['searched_id']) . '</strong>' : '' ?> sai hoặc không tồn tại trên hệ thống. Vui lòng kiểm tra lại mã số hồ sơ!
            </div>
            <a href="/?view=records" style="color: #991b1b; text-decoration: underline; font-weight: 700; font-size: 12px; margin-left: 12px;">Đóng</a>
          </div>
        <?php elseif (isset($_GET['login_success'])): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            <strong>Đăng nhập thành công!</strong> Chào mừng <strong><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></strong> (<code><?= htmlspecialchars($currentUser['role'] ?? '') ?></code>) quay trở lại hệ thống MediRecord EMR.
          </div>
        <?php elseif (isset($_GET['reg_success'])): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            <strong>Đăng ký thành công!</strong> Tài khoản bệnh nhân của bạn đã được khởi tạo và đăng nhập tự động. Chúc bạn một ngày tốt lành!
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Hồ Sơ Bệnh Án Điện Tử (Danh Sách Khám Bệnh)</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Đang hiển thị danh sách hồ sơ thuộc thẩm quyền tiếp cận của tài khoản hiện tại.
              </div>
            </div>
            <div style="display: flex; gap: 8px;">
              <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
                <a href="/?view=create_record" class="btn btn-primary">+ Lập Bệnh Án Mới</a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Hộp tra cứu nhanh theo ID (Không giới hạn số lượng bệnh án, bắt lỗi rõ ràng) -->
          <div class="table-toolbar-responsive" style="background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div>
              <div style="font-weight: 700; font-size: 13px; color: #0f172a;">TRA CỨU & MỞ HỒ SƠ BỆNH ÁN THEO MÃ ID:</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                Nhập mã bệnh án (Ví dụ: ID <code>1</code>, <code>2</code> của An, hoặc ID <code>3</code> của Bình) để kiểm tra phản hồi phân quyền. Không giới hạn số lượng hồ sơ.
              </div>
            </div>
            <form action="/" method="GET" style="display: flex; gap: 8px; align-items: center;">
              <input type="hidden" name="view" value="record">
              <input type="number" name="id" min="1" placeholder="Nhập ID..." value="<?= isset($_GET['searched_id']) ? htmlspecialchars($_GET['searched_id']) : '3' ?>" style="width: 120px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-weight: 700; text-align: center;">
              <button type="submit" class="btn btn-primary" style="font-size: 12px;">Tra Cứu Bệnh Án</button>
            </form>
          </div>

          <div class="table-responsive">
            <div class="table-mobile-hint">Vuốt ngang để xem đủ 8 cột dữ liệu &raquo;</div>
            <table id="recordsTable">
              <thead>
                <tr>
                  <th style="width: 70px;">Mã HS</th>
                  <th>Tiêu Đề Bệnh Án</th>
                  <th>Khoa Khám</th>
                  <th>Bệnh Nhân Sở Hữu</th>
                  <th>Bác Sĩ Phụ Trách</th>
                  <th>Chẩn Đoán Sơ Bộ</th>
                  <th>Ngày Lập</th>
                  <th style="text-align: right; min-width: 120px; white-space: nowrap;">Thao Tác</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($displayRecords)): ?>
                  <tr>
                    <td colspan="8" style="text-align: center; padding: 36px 16px; color: var(--muted); font-size: 13px;">
                      <?php if (!$currentUser): ?>
                        Bạn chưa đăng nhập. Vui lòng <a href="/?view=login" style="color: var(--primary); font-weight: 700;">đăng nhập</a> để xem hồ sơ bệnh án.
                      <?php else: ?>
                        Hiện tại bạn chưa có hồ sơ bệnh án nào được ghi nhận trên hệ thống.
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($displayRecords as $rec): ?>
                    <?php
                      $isOwn = ($currentUser && checkRecordOwnerMatch($rec['owner_username'], $currentUser['username']));
                      $isStaff = ($currentUser && in_array($currentUser['role'], ['doctor', 'admin', 'auditor'], true));
                    ?>
                    <tr>
                      <td><code>#<?= $rec['id'] ?></code></td>
                      <td>
                        <strong><?= htmlspecialchars($rec['title']) ?></strong>

                      </td>
                      <td><?= htmlspecialchars($rec['department']) ?></td>
                      <td>
                        <code><?= htmlspecialchars($rec['owner_username']) ?></code>
                        <?php if ($isOwn): ?>
                          <span style="font-size: 10px; background: #dcfce7; color: #15803d; padding: 1px 5px; border-radius: 6px; font-weight: 700; margin-left: 2px;">Của bạn</span>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($rec['doctor_name'] ?? 'BS. CKI Lê Hoàng Minh') ?></td>
                      <td><span style="color: #0369a1;"><?= htmlspecialchars($rec['diagnosis'] ?? 'Đang cập nhật') ?></span></td>
                      <td style="white-space: nowrap;"><?= $rec['created_at'] ?></td>
                      <td style="text-align: right; min-width: 150px; white-space: nowrap;">
                        <a href="/?view=record&id=<?= $rec['id'] ?>" class="btn btn-outline" style="padding: 5px 10px; font-size: 12px; white-space: nowrap;">Xem</a>
                        <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
                          <?php
                            $canDelRec = ($currentUser['role'] === 'admin')
                              || (($rec['doctor_username'] ?? '') === $currentUser['username'])
                              || (($rec['department'] ?? '') === ($currentUser['department'] ?? ''));
                          ?>
                          <?php if ($canDelRec): ?>
                            <a href="/?action=delete_record&id=<?= $rec['id'] ?>"
                               onclick="return confirmWithHold(event, { title: 'Xác Nhận Xoá Bệnh Án', message: 'Bạn có chắc chắn muốn xoá vĩnh viễn hồ sơ bệnh án #<?= $rec['id'] ?> (<?= htmlspecialchars($rec['title']) ?>)? Hành động này không thể hoàn tác!' })"
                               class="btn btn-danger"
                               style="padding: 5px 10px; font-size: 12px; margin-left: 4px; text-decoration: none;">Xóa</a>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div id="recordsPagination" class="pagination-wrap"></div>
        </div>
