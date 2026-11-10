        <?php if (isset($_GET['msg'])): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
            <?php if ($_GET['msg'] === 'user_added'): ?>
              <strong>Thành công:</strong> Đã tạo mới tài khoản người dùng thành công!
            <?php elseif ($_GET['msg'] === 'user_updated'): ?>
              <strong>Cập nhật:</strong> Đã lưu thay đổi thông tin người dùng thành công!
            <?php elseif ($_GET['msg'] === 'user_deleted'): ?>
              <strong>Đã xóa:</strong> Đã xóa tài khoản người dùng khỏi hệ thống!
            <?php endif; ?>
          </div>
        <?php elseif (isset($_GET['err'])): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
            <?php if ($_GET['err'] === 'cannot_delete_self'): ?>
              <strong>Lỗi:</strong> Không thể tự xóa tài khoản của chính mình khi đang đăng nhập!
            <?php elseif ($_GET['err'] === 'missing_fields'): ?>
              <strong>Lỗi:</strong> Vui lòng nhập đầy đủ tên đăng nhập, mật khẩu và họ tên!
            <?php elseif ($_GET['err'] === 'username_taken'): ?>
              <strong>Lỗi:</strong> Tên đăng nhập đã tồn tại trong hệ thống! Vui lòng chọn username khác.
            <?php elseif ($_GET['err'] === 'user_not_found'): ?>
              <strong>Lỗi:</strong> Không tìm thấy thông tin tài khoản cần xử lý!
            <?php else: ?>
              <strong>Lỗi:</strong> <?= htmlspecialchars($_GET['err']) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Quản Trị Người Dùng & Nhân Sự Y Tế (Admin CRUD)</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Tổng số tài khoản: <strong id="totalUserCount"><?= count($mockUsers) ?></strong> người dùng
              </div>
            </div>
            <div class="user-search-actions">
              <input type="text" id="userSearchInput" onkeyup="filterUsers()" placeholder="Tìm kiếm nhanh họ tên, username..." style="padding: 7px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 12px; width: 240px;">
              <button onclick="openAddUserModal()" class="btn btn-primary" style="font-size: 12px;">+ Thêm Người Dùng</button>
            </div>
          </div>

          <div class="table-responsive">
            <div class="table-mobile-hint">Vuốt ngang để xem toàn bộ thông tin & thao tác &raquo;</div>
            <table id="userTable">
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Họ và Tên</th>
                  <th>Vai trò</th>
                  <th>Khoa / Phòng</th>
                  <th style="text-align: right; min-width: 120px; white-space: nowrap;">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($mockUsers as $u): ?>
                  <tr>
                    <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                    <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                    <td>
                      <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; background: #e0f2fe; color: #0369a1;">
                        <?= htmlspecialchars($u['role']) ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($u['department'] ?? 'Chưa cập nhật') ?></td>
                    <td style="text-align: right; min-width: 120px; white-space: nowrap;">
                      <button onclick="openEditUserModal('<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= htmlspecialchars($u['role']) ?>', '<?= htmlspecialchars(addslashes($u['department'] ?? '')) ?>')" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;">Sửa</button>
                      <?php if ($u['username'] !== 'admin_bv'): ?>
                        <a href="/?action=delete_user&username=<?= urlencode($u['username']) ?>" onclick="return confirmWithHold(event, { title: 'Xác Nhận Xoá Người Dùng', message: 'Bạn có chắc chắn muốn xóa tài khoản <?= htmlspecialchars($u['username']) ?>? Người dùng này sẽ mất toàn bộ quyền truy cập hệ thống.' })" class="btn btn-danger" style="padding: 3px 8px; font-size: 11px;">Xóa</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div id="usersPagination" class="pagination-wrap"></div>
        </div>

        <!-- MODAL THÊM NGƯỜI DÙNG -->
        <div id="addUserModal" class="modal-overlay" style="display: none;">
          <div class="modal-box">
            <div class="card-head" style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
              <div class="card-title">Thêm Tài Khoản Người Dùng Mới</div>
              <button type="button" onclick="closeAddUserModal()" class="btn btn-outline" style="padding: 2px 8px;">&times;</button>
            </div>
            <form action="/?action=add_user" method="POST">
              <div class="form-group">
                <label>Tên đăng nhập (username): <span style="color:#ef4444;">*</span></label>
                <input type="text" name="username" placeholder="VD: bs_dung, bn_ha..." required>
              </div>
              <div class="form-group">
                <label>Mật khẩu khởi tạo: <span style="color:#ef4444;">*</span></label>
                <input type="password" name="password" value="TestPass@2026" required>
              </div>
              <div class="form-group">
                <label>Họ và tên hiển thị: <span style="color:#ef4444;">*</span></label>
                <input type="text" name="full_name" placeholder="VD: BS. Lê Tiến Dũng..." required>
              </div>
              <div class="form-group">
                <label>Vai trò hệ thống: <span style="color:#ef4444;">*</span></label>
                <select name="role" required>
                  <option value="doctor">Bác sĩ khám bệnh (doctor)</option>
                  <option value="member">Bệnh nhân (member/patient)</option>
                  <option value="admin">Quản trị viên (admin)</option>
                  <option value="auditor">Kiểm toán an toàn (auditor)</option>
                </select>
              </div>
              <div class="form-group">
                <label>Khoa phòng / Đơn vị:</label>
                <input type="text" name="department" value="Khoa Khám Bệnh Chuyên Sâu" required>
              </div>
              <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeAddUserModal()" class="btn btn-outline">Hủy Bỏ</button>
                <button type="submit" class="btn btn-primary">Tạo Người Dùng Mới</button>
              </div>
            </form>
          </div>
        </div>

        <!-- MODAL SỬA NGƯỜI DÙNG -->
        <div id="editUserModal" class="modal-overlay" style="display: none;">
          <div class="modal-box">
            <div class="card-head" style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
              <div class="card-title">Chỉnh Sửa Thông Tin Người Dùng</div>
              <button type="button" onclick="closeEditUserModal()" class="btn btn-outline" style="padding: 2px 8px;">&times;</button>
            </div>
            <form action="/?action=edit_user" method="POST">
              <input type="hidden" id="editUsername" name="username">
              <div class="form-group">
                <label>Tên đăng nhập:</label>
                <input type="text" id="displayEditUsername" disabled style="background: #f1f5f9; cursor: not-allowed;">
              </div>
              <div class="form-group">
                <label>Họ và tên hiển thị: <span style="color:#ef4444;">*</span></label>
                <input type="text" id="editFullName" name="full_name" required>
              </div>
              <div class="form-group">
                <label>Vai trò (Role): <span style="color:#ef4444;">*</span></label>
                <select id="editRole" name="role" required>
                  <option value="doctor">Bác sĩ khám bệnh (doctor)</option>
                  <option value="member">Bệnh nhân (member/patient)</option>
                  <option value="admin">Quản trị viên (admin)</option>
                  <option value="auditor">Kiểm toán an toàn (auditor)</option>
                </select>
              </div>
              <div class="form-group">
                <label>Khoa phòng / Đơn vị:</label>
                <input type="text" id="editDept" name="department" required>
              </div>
              <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeEditUserModal()" class="btn btn-outline">Hủy Bỏ</button>
                <button type="submit" class="btn btn-primary">Cập Nhật Người Dùng</button>
              </div>
            </form>
          </div>
        </div>

