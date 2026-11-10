        <div class="card" style="max-width: 500px; margin: 30px auto; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border-radius: 12px;">
          <div class="card-head" style="border-bottom: 2px solid #e2e8f0; padding-bottom: 14px;">
            <div>
              <div class="card-title" style="font-size: 18px; color: #0f172a;">Đăng Ký Tài Khoản Y Tế Mới</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Khởi tạo tài khoản Bệnh nhân hoặc Bác sĩ mới vào hệ thống</div>
            </div>
            <span style="font-size: 11px; background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 20px; font-weight: 700;">THÀNH VIÊN</span>
          </div>

          <?php if (isset($_GET['err'])): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px;">
              <?php if ($_GET['err'] === 'username_taken'): ?>
                <strong>Đăng ký thất bại:</strong> Tên đăng nhập này đã được sử dụng! Vui lòng chọn tên đăng nhập khác.
              <?php elseif ($_GET['err'] === 'password_mismatch'): ?>
                <strong>Đăng ký thất bại:</strong> Mật khẩu xác nhận không khớp nhau! Vui lòng kiểm tra lại.
              <?php elseif ($_GET['err'] === 'missing_fields'): ?>
                <strong>Đăng ký thất bại:</strong> Vui lòng điền đầy đủ tất cả các trường thông tin bắt buộc!
              <?php else: ?>
                <strong>Thông báo:</strong> <?= htmlspecialchars($_GET['err']) ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <form action="/?action=register_submit" method="POST">
            <div class="form-group">
              <label for="regUsername">Tên đăng nhập (Username): <span style="color:#ef4444;">*</span></label>
              <input type="text" id="regUsername" name="username" value="<?= htmlspecialchars($_GET['u'] ?? '') ?>" placeholder="VD: bn_tuan, bs_hoa..." required autofocus>
            </div>

            <div class="form-group">
              <label for="regFullName">Họ và tên đầy đủ: <span style="color:#ef4444;">*</span></label>
              <input type="text" id="regFullName" name="full_name" value="<?= htmlspecialchars($_GET['fn'] ?? '') ?>" placeholder="VD: Nguyễn Tuấn Anh" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label for="regRole">Vai trò tài khoản: <span style="color:#ef4444;">*</span></label>
                <select id="regRole" name="role" required>
                  <option value="member">Bệnh nhân (Patient)</option>
                  <option value="doctor">Bác sĩ khám (Doctor)</option>
                </select>
              </div>
              <div class="form-group">
                <label for="regDept">Khoa phòng / Đơn vị:</label>
                <input type="text" id="regDept" name="department" value="Bệnh nhân ngoại trú" placeholder="VD: Khoa Khám Bệnh">
              </div>
            </div>

            <div class="form-group">
              <label for="regPassword">Mật khẩu: <span style="color:#ef4444;">*</span></label>
              <input type="password" id="regPassword" name="password" placeholder="Tối thiểu 6 ký tự..." required>
            </div>

            <div class="form-group">
              <label for="regConfirm">Xác nhận mật khẩu: <span style="color:#ef4444;">*</span></label>
              <input type="password" id="regConfirm" name="confirm_password" placeholder="Nhập lại mật khẩu..." required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; font-size: 14px; font-weight: 700; margin-top: 6px;">Hoàn Tất Đăng Ký</button>
          </form>

          <div style="margin-top: 18px; text-align: center; font-size: 13px; color: #64748b;">
            Đã có tài khoản? <a href="/?view=login" style="color: #0284c7; font-weight: 700; text-decoration: none;">Đăng nhập ngay</a>
          </div>
        </div>
