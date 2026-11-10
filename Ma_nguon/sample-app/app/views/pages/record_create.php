        <div class="card" style="max-width: 720px; margin: 0 auto;">
          <div class="card-head">
            <div>
              <div class="card-title">Lập Hồ Sơ Bệnh Án Mới Cho Bệnh Nhân</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Bác sĩ phụ trách: <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<code><?= htmlspecialchars($currentUser['department'] ?? 'Khoa Khám Bệnh') ?></code>)
              </div>
            </div>
            <a href="/?view=records" class="btn btn-outline">Quay lại danh sách</a>
          </div>

          <?php if (isset($_GET['err']) && $_GET['err'] === 'missing_fields'): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin: 0 0 18px 0;">
              <strong>Lỗi:</strong> Vui lòng điền đầy đủ tiêu đề, chuyên khoa, chẩn đoán sơ bộ và nội dung phác đồ điều trị!
            </div>
          <?php endif; ?>

          <?php
            // Lấy danh sách bệnh nhân và các tài khoản người dùng tùy chỉnh
            $allPatientUsers = [];
            $otherUsers = [];
            foreach ($mockUsers as $uKey => $uVal) {
                if (($uVal['role'] ?? '') === 'member') {
                    $allPatientUsers[$uKey] = $uVal;
                } elseif (!in_array($uKey, ['admin_bv', 'bs_minh', 'tester_sec'], true)) {
                    $otherUsers[$uKey] = $uVal;
                }
            }
            // Đảm bảo luôn có tối thiểu 2 tài khoản mẫu nếu danh sách trống
            if (empty($allPatientUsers)) {
                $allPatientUsers['bn_an'] = ['username' => 'bn_an', 'full_name' => 'Nguyễn Văn An', 'department' => 'Điều trị ngoại trú'];
                $allPatientUsers['bn_binh'] = ['username' => 'bn_binh', 'full_name' => 'Trần Thị Bình', 'department' => 'Khoa Ung Bướu'];
            }

            // Danh sách các khoa / phòng điều trị lâm sàng chuẩn
            $presetDepartments = [
                'Khoa Tim Mạch Can Thiệp',
                'Khoa Khám Bệnh Yêu Cầu',
                'Khoa Ung Bướu & Hóa Trị Liệu',
                'Khoa Tim Mạch & Nội Tiết',
                'Khoa Hồi Sức Tích Cực & Chống Độc',
                'Khoa Cấp Cứu & Đột Quỵ',
                'Khoa Tiêu Hóa - Gan Mật',
                'Khoa Dược Lâm Sàng',
                'Khoa Nội Tiết & Đái Tháo Đường',
                'Khoa Ngoại Tổng Hợp',
                'Khoa Chấn Thương Chỉnh Hình',
                'Khoa Thần Kinh & Tâm Thần Y Học',
                'Khoa Nhi & Sơ Sinh',
                'Khoa Mắt & Tai Mũi Họng',
                'Khoa Chẩn Đoán Hình Ảnh',
                'Khoa Huyết Học - Truyền Máu',
                'Khoa Phục Hồi Chức Năng'
            ];
            $currentDept = $currentUser['department'] ?? 'Khoa Tim Mạch Can Thiệp';
            if (!in_array($currentDept, $presetDepartments, true) && !empty($currentDept)) {
                array_unshift($presetDepartments, $currentDept);
            }
          ?>

          <form action="/?action=create_record_submit" method="POST" onsubmit="return confirmFormWithHold(event, this, { title: 'Xác Nhận Lập Bệnh Án', message: 'Bạn có chắc chắn muốn tạo và phát hành hồ sơ bệnh án mới này vào cơ sở dữ liệu y tế?' })">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
              <div class="form-group">
                <label for="owner_username">Bệnh nhân thụ hưởng (Chọn tài khoản):</label>
                <select id="owner_username" name="owner_username" required style="width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #fff;">
                  <option value="">-- Chọn bệnh nhân thụ hưởng --</option>
                  <optgroup label="Danh sách Bệnh nhân">
                    <?php foreach ($allPatientUsers as $p): ?>
                      <option value="<?= htmlspecialchars($p['username']) ?>">
                        <?= htmlspecialchars($p['full_name'] ?? $p['username']) ?> (<?= htmlspecialchars($p['username']) ?><?= !empty($p['department']) ? ' - ' . htmlspecialchars($p['department']) : '' ?>)
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php if (!empty($otherUsers)): ?>
                    <optgroup label="Tài khoản khác / Tạo mới">
                      <?php foreach ($otherUsers as $ou): ?>
                        <option value="<?= htmlspecialchars($ou['username']) ?>">
                          <?= htmlspecialchars($ou['full_name'] ?? $ou['username']) ?> (<?= htmlspecialchars($ou['username']) ?> - <?= htmlspecialchars($ou['role'] ?? 'member') ?>)
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endif; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="department">Khoa / Phòng điều trị:</label>
                <select id="department" name="department" required style="width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #fff;">
                  <?php foreach ($presetDepartments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= ($d === $currentDept) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($d) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Tiêu đề hồ sơ bệnh án:</label>
              <input type="text" name="title" placeholder="Ví dụ: Bệnh án khám định kỳ chuyên khoa Tim Mạch..." required>
            </div>

            <div class="form-group">
              <label>Chẩn đoán sơ bộ ban đầu:</label>
              <input type="text" name="diagnosis" placeholder="Ví dụ: Rối loạn nhịp xoang, tăng huyết áp độ 1..." required>
            </div>

            <div class="form-group">
              <label>Nội dung phác đồ điều trị, kết quả lâm sàng & đơn thuốc:</label>
              <textarea name="content" rows="6" placeholder="Chi tiết khám lâm sàng, sinh hiệu, chỉ định dùng thuốc..." required></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
              <a href="/?view=records" class="btn btn-outline">Hủy Bỏ</a>
              <button type="submit" class="btn btn-primary">Lưu & Phát Hành Bệnh Án</button>
            </div>
          </form>
        </div>

