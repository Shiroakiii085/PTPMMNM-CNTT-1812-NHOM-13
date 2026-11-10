        <div class="card" style="max-width: 480px; margin: 30px auto; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border-radius: 12px;">
          <div class="card-head" style="border-bottom: 2px solid #e2e8f0; padding-bottom: 14px;">
            <div>
              <div class="card-title" style="font-size: 18px; color: #0f172a;">Đăng Nhập MediRecord EMR</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Hệ thống Quản lý Bệnh án Điện tử - Bệnh viện Bạch Mai</div>
            </div>
            <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 20px; font-weight: 700;">CỔNG 8205</span>
          </div>

          <?php if ($currentUser): ?>
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
              <div>
                Đang đăng nhập: <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<code><?= htmlspecialchars($currentUser['username']) ?></code>)
              </div>
              <div style="display: flex; gap: 6px;">
                <a href="/?view=records" class="btn btn-primary" style="font-size: 11px; padding: 4px 8px;">Hồ Sơ</a>
                <a href="/?action=logout" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; color: #b91c1c; border-color: #fca5a5;">Đăng Xuất</a>
              </div>
            </div>
          <?php endif; ?>

          <?php if (isset($_GET['err'])): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px;">
              <?php if (in_array($_GET['err'], ['invalid', 'invalid_credentials'], true)): ?>
                <strong>Đăng nhập thất bại:</strong> Tên đăng nhập hoặc mật khẩu không chính xác! Vui lòng thử lại.
              <?php elseif ($_GET['err'] === 'missing_fields'): ?>
                <strong>Lỗi:</strong> Vui lòng điền đầy đủ tên đăng nhập và mật khẩu!
              <?php else: ?>
                <strong>Thông báo lỗi:</strong> <?= htmlspecialchars($_GET['err']) ?>
              <?php endif; ?>
            </div>
          <?php elseif (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'require_login'): ?>
              <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px;">
                <strong>Yêu cầu đăng nhập:</strong> Vui lòng đăng nhập tài khoản để truy cập thông tin bệnh án y tế.
              </div>
            <?php elseif ($_GET['msg'] === 'logged_out'): ?>
              <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px;">
                <strong>Đã đăng xuất:</strong> Bạn đã đăng xuất an toàn khỏi hệ thống!
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <form action="/?action=login_submit" method="POST">
            <div class="form-group">
              <label for="loginUsername">Tên đăng nhập (Username):</label>
              <input type="text" id="loginUsername" name="username" value="<?= htmlspecialchars($_GET['u'] ?? '') ?>" placeholder="VD: bs_minh, bn_an, admin_bv" required autofocus>
            </div>
            <div class="form-group">
              <label for="loginPassword">Mật khẩu truy cập:</label>
              <input type="password" id="loginPassword" name="password" value="TestPass@2026" placeholder="Nhập mật khẩu..." required>
              <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">(Mật khẩu mặc định của các tài khoản mẫu: <code>TestPass@2026</code>)</div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; font-size: 14px; font-weight: 700; margin-top: 6px;">Đăng Nhập Ngay</button>
          </form>

          <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border);">
            <div style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px;">Điền nhanh tài khoản kiểm thử:</div>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
              <button type="button" onclick="fillLogin('admin_bv', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #fca5a5; color: #b91c1c;">Admin</button>
              <button type="button" onclick="fillLogin('bs_minh', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #93c5fd; color: #1d4ed8;">Bác sĩ Minh</button>
              <button type="button" onclick="fillLogin('bn_an', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #86efac; color: #15803d;">Bệnh nhân An</button>
              <button type="button" onclick="fillLogin('bn_binh', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #86efac; color: #15803d;">Bệnh nhân Bình</button>
              <button type="button" onclick="fillLogin('tester_sec', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #fde047; color: #b45309;">Tester Nam</button>
            </div>
          </div>

          <div style="margin-top: 18px; text-align: center; font-size: 13px; color: #64748b;">
            Chưa có tài khoản y tế? <a href="/?view=register" style="color: #0284c7; font-weight: 700; text-decoration: none;">Đăng ký tài khoản mới</a>
          </div>
        </div>
