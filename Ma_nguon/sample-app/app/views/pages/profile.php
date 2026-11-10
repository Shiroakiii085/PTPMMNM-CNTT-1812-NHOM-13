        <?php
          // Tính toán số lượng hồ sơ bệnh án người dùng có quyền truy cập
          $myAccessibleRecordsCount = 0;
          if (!empty($mockRecords)) {
              foreach ($mockRecords as $r) {
                  if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'auditor') {
                      $myAccessibleRecordsCount++;
                  } elseif ($currentUser['role'] === 'doctor') {
                      if (($r['doctor_username'] ?? '') === $currentUser['username'] || ($r['department'] ?? '') === ($currentUser['department'] ?? '')) {
                          $myAccessibleRecordsCount++;
                      }
                  } elseif ($currentUser['role'] === 'member') {
                      if (checkRecordOwnerMatch($r['owner_username'] ?? '', $currentUser['username'])) {
                          $myAccessibleRecordsCount++;
                      }
                  }
              }
          }

          $roleMap = [
              'admin' => ['label' => 'Quản Trị Viên Hệ Thống', 'bg' => '#ede9fe', 'color' => '#6d28d9', 'badge' => 'ADMIN'],
              'doctor' => ['label' => 'Bác Sĩ Điều Trị', 'bg' => '#e0f2fe', 'color' => '#0369a1', 'badge' => 'DOCTOR'],
              'member' => ['label' => 'Bệnh Nhân', 'bg' => '#ecfdf5', 'color' => '#047857', 'badge' => 'PATIENT'],
              'auditor' => ['label' => 'Kỹ Sư An Ninh / Kiểm Thử', 'bg' => '#fef3c7', 'color' => '#b45309', 'badge' => 'AUDITOR'],
          ];
          $roleInfo = $roleMap[$currentUser['role']] ?? ['label' => $currentUser['role'], 'bg' => '#f1f5f9', 'color' => '#475569', 'badge' => strtoupper($currentUser['role'])];

          $userInitials = function_exists('mb_substr') ? mb_substr($currentUser['full_name'], 0, 2, 'UTF-8') : substr($currentUser['full_name'], 0, 2);
        ?>

        <!-- Breadcrumb / Header -->
        <div style="margin-bottom: 24px;">
          <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 6px;">
            <a href="/?view=records" style="color: var(--primary); text-decoration: none;">Bệnh Án</a>
            <span>/</span>
            <span style="color: #0f172a; font-weight: 600;">Hồ Sơ Cá Nhân & Quản Trị Tài Khoản</span>
          </div>
          <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0;">
            Hồ Sơ Cá Nhân & Quản Lý Bảo Mật
          </h1>
          <p style="color: var(--muted); font-size: 13px; margin: 4px 0 0 0;">
            Quản lý thông tin định danh y tế, chứng thực vai trò phân quyền ABAC và thiết lập mật khẩu bảo vệ tài khoản.
          </p>
        </div>

        <!-- Thông báo Trạng thái (Flash Alerts) -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'profile_updated'): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; display: flex; align-items: center; gap: 8px;">
            <span style="font-weight: 700;">✓ Thành công:</span> Thông tin hồ sơ cá nhân của bạn đã được cập nhật đồng bộ trên hệ thống!
          </div>
        <?php elseif (isset($_GET['msg']) && ($_GET['msg'] === 'password_changed' || $_GET['msg'] === 'password_updated')): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; display: flex; align-items: center; gap: 8px;">
            <span style="font-weight: 700;">✓ Thành công:</span> Mật khẩu truy cập đã được đổi thành công và mã hóa bảo mật an toàn!
          </div>
        <?php elseif (isset($_GET['err'])): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
            <strong>Lỗi:</strong>
            <?php
              $errCodes = [
                  'wrong_current_password' => 'Mật khẩu hiện tại không chính xác! Vui lòng kiểm tra lại.',
                  'pass_mismatch' => 'Mật khẩu mới và mật khẩu xác nhận không trùng khớp nhau!',
                  'pass_too_short' => 'Mật khẩu mới phải có độ dài tối thiểu từ 6 ký tự trở lên để đảm bảo an toàn.',
                  'missing_fullname' => 'Họ và tên hiển thị không được để trống!',
                  'missing_pass_fields' => 'Vui lòng nhập đầy đủ mật khẩu hiện tại, mật khẩu mới và xác nhận mật khẩu.',
                  'unauthorized' => 'Phiên làm việc hết hạn hoặc không có thẩm quyền truy cập!'
              ];
              echo htmlspecialchars($errCodes[$_GET['err']] ?? 'Đã xảy ra lỗi trong quá trình xử lý yêu cầu.');
            ?>
          </div>
        <?php endif; ?>

        <div class="profile-layout-grid">
          <!-- CỘT TRÁI: THẺ ĐỊNH DANH (IDENTITY CARD) -->
          <div style="display: flex; flex-direction: column; gap: 18px;">
            <div class="card" style="margin: 0; text-align: center; padding: 28px 20px;">
              <div style="width: 84px; height: 84px; border-radius: 50%; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; font-size: 30px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35); text-transform: uppercase;">
                <?= htmlspecialchars($userInitials) ?>
              </div>
              <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">
                <?= htmlspecialchars($currentUser['full_name']) ?>
              </h2>
              <div style="font-size: 13px; color: #64748b; font-family: monospace; margin-bottom: 12px;">
                @<?= htmlspecialchars($currentUser['username']) ?>
              </div>

              <div>
                <span style="font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px; background: <?= $roleInfo['bg'] ?>; color: <?= $roleInfo['color'] ?>; display: inline-block;">
                  <?= htmlspecialchars($roleInfo['label']) ?>
                </span>
              </div>

              <div style="margin-top: 20px; padding-top: 18px; border-top: 1px solid #f1f5f9; text-align: left; display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b;">Khoa / Phòng ban:</span>
                  <strong style="color: #0f172a; text-align: right;"><?= htmlspecialchars($currentUser['department'] ?? 'Chưa chỉ định') ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b;">Hồ sơ khả dụng:</span>
                  <span style="background: #e2e8f0; color: #1e293b; font-weight: 700; padding: 2px 8px; border-radius: 10px; font-size: 12px;">
                    <?= $myAccessibleRecordsCount ?> bệnh án
                  </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b;">Chính sách phân quyền:</span>
                  <span style="color: #059669; font-weight: 600;">ABAC + RBAC</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b;">Trạng thái phiên:</span>
                  <span style="color: #0284c7; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                    Đang hoạt động
                  </span>
                </div>
              </div>

              <!-- Xuất dữ liệu cá nhân dạng JSON có nút Giữ để Mở khoá -->
              <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid #f1f5f9;">
                <a href="/?action=export_my_records_json"
                   onclick="return confirmWithHold(event, { title: 'Tải Bản Sao Bệnh Án (JSON)', message: 'Tải về toàn bộ hồ sơ bệnh án bạn có thẩm quyền truy cập dưới định dạng tệp JSON bảo mật.' })"
                   class="btn btn-outline"
                   style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 600; text-decoration: none; padding: 9px 12px;">
                  <span>⬇ Tải Bản Sao Bệnh Án (JSON)</span>
                </a>
              </div>
            </div>

            <!-- Thẻ mẹo an toàn mật khẩu -->
            <div class="card" style="margin: 0; background: #f8fafc; border: 1px solid #e2e8f0; padding: 18px;">
              <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <span>🛡 Tiêu Chuẩn Bảo Mật Y Tế</span>
              </div>
              <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #64748b; line-height: 1.6;">
                <li>Mật khẩu mới yêu cầu tối thiểu <strong>6 ký tự</strong> trở lên.</li>
                <li>Không chia sẻ tài khoản y bác sĩ với bất kỳ người nào khác.</li>
                <li>Tất cả thao tác chỉnh sửa và phân quyền đều được kiểm soát bởi quy tắc ABAC.</li>
              </ul>
            </div>
          </div>

          <!-- CỘT PHẢI: FORM CHỈNH SỬA THÔNG TIN & ĐỔI MẬT KHẨU -->
          <div style="display: flex; flex-direction: column; gap: 20px;">
            <!-- CARD 1: CẬP NHẬT THÔNG TIN HỒ SƠ -->
            <div class="card" style="margin: 0;">
              <div class="card-head" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 18px;">
                <div>
                  <div class="card-title" style="font-size: 16px;">Thông Tin Cá Nhân & Chuyên Khoa</div>
                  <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Cập nhật họ tên hiển thị và khoa/phòng điều trị phụ trách</div>
                </div>
                <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 6px; font-weight: 700;">HỒ SƠ</span>
              </div>

              <form action="/?action=update_profile" method="POST" onsubmit="return confirmFormWithHold(event, this, { title: 'Xác Nhận Lưu Thông Tin', message: 'Bạn có chắc chắn muốn cập nhật họ tên và khoa/phòng cho hồ sơ tài khoản này?' })">
                <div class="form-group">
                  <label style="font-weight: 600; font-size: 13px; color: #334155;">Tên tài khoản (Username):</label>
                  <input type="text" value="<?= htmlspecialchars($currentUser['username']) ?>" disabled style="background: #f1f5f9; color: #64748b; cursor: not-allowed; font-family: monospace;">
                  <small style="font-size: 11px; color: #94a3b8; display: block; margin-top: 4px;">Tên định danh cố định trong hệ thống EMR, không thể thay đổi.</small>
                </div>

                <div class="form-group">
                  <label style="font-weight: 600; font-size: 13px; color: #334155;">Họ và tên hiển thị (*):</label>
                  <input type="text" name="full_name" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required placeholder="Ví dụ: BS.CKII Trần Văn Minh" style="padding: 10px 12px;">
                </div>

                <div class="form-group">
                  <label style="font-weight: 600; font-size: 13px; color: #334155;">Khoa / Phòng điều trị phụ trách:</label>
                  <?php
                    $profileDepts = [
                        'Khoa Tim Mạch Can Thiệp',
                        'Khoa Ung Bướu & Hóa Trị Liệu',
                        'Khoa Tim Mạch & Nội Tiết',
                        'Khoa Hồi Sức Tích Cực & Chống Độc',
                        'Khoa Cấp Cứu & Đột Quỵ',
                        'Khoa Tiêu Hóa - Gan Mật',
                        'Khoa Dược Lâm Sàng',
                        'Khoa Nội Tiết & Đái Tháo Đường',
                        'Khoa Ngoại Tổng Hợp',
                        'Khoa Chấn Thương Chỉnh Hình',
                        'Khoa Khám Bệnh Yêu Cầu',
                        'Phòng CNTT & An Ninh Mạng',
                        'Trung Tâm Kiểm Định & Đánh Giá Độc Lập',
                        'Điều trị ngoại trú',
                    ];
                    $curUserDept = $currentUser['department'] ?? '';
                  ?>
                  <select name="department" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #fff;">
                    <?php if (!in_array($curUserDept, $profileDepts, true) && $curUserDept !== ''): ?>
                      <option value="<?= htmlspecialchars($curUserDept) ?>" selected><?= htmlspecialchars($curUserDept) ?></option>
                    <?php endif; ?>
                    <?php foreach ($profileDepts as $d): ?>
                      <option value="<?= htmlspecialchars($d) ?>" <?= ($curUserDept === $d) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 18px;">
                  <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-size: 13px; font-weight: 600;">
                    Lưu Thay Đổi Thông Tin
                  </button>
                </div>
              </form>
            </div>

            <!-- CARD 2: ĐỔI MẬT KHẨU TRUY CẬP -->
            <div class="card" style="margin: 0;">
              <div class="card-head" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 18px;">
                <div>
                  <div class="card-title" style="font-size: 16px;">Đổi Mật Khẩu Đăng Nhập</div>
                  <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Cập nhật mật khẩu để bảo vệ tài khoản và dữ liệu bệnh án</div>
                </div>
                <span style="font-size: 11px; background: #fef3c7; color: #b45309; padding: 3px 8px; border-radius: 6px; font-weight: 700;">BẢO MẬT</span>
              </div>

              <form action="/?action=change_password" method="POST" onsubmit="return confirmFormWithHold(event, this, { title: 'Xác Nhận Đổi Mật Khẩu', message: 'Bạn có chắc chắn muốn thay đổi mật khẩu truy cập? Sau khi đổi, bạn sẽ cần dùng mật khẩu mới này để đăng nhập.' })">
                <div class="form-group">
                  <label style="font-weight: 600; font-size: 13px; color: #334155;">Mật khẩu hiện tại (*):</label>
                  <input type="password" name="current_password" required placeholder="Nhập mật khẩu đang sử dụng" style="padding: 10px 12px;">
                  <small style="font-size: 11px; color: #94a3b8; display: block; margin-top: 4px;">Mật khẩu mặc định hệ thống: <code>TestPass@2026</code> hoặc tên username.</small>
                </div>

                <div class="form-group">
                  <label style="font-weight: 600; font-size: 13px; color: #334155;">Mật khẩu mới (*):</label>
                  <input type="password" name="new_password" required minlength="6" placeholder="Tối thiểu 6 ký tự" style="padding: 10px 12px;">
                </div>

                <div class="form-group">
                  <label style="font-weight: 600; font-size: 13px; color: #334155;">Xác nhận lại mật khẩu mới (*):</label>
                  <input type="password" name="confirm_password" required minlength="6" placeholder="Nhập lại mật khẩu mới một lần nữa" style="padding: 10px 12px;">
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 18px;">
                  <button type="submit" class="btn btn-primary" style="background: #0284c7; border-color: #0284c7; padding: 10px 20px; font-size: 13px; font-weight: 600;">
                    Cập Nhật Mật Khẩu Mới
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
