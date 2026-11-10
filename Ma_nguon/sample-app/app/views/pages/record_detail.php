        <?php if (isset($_GET['err']) && $_GET['err'] === 'patient_read_only'): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            <strong>Từ chối quyền chỉnh sửa:</strong> Bệnh nhân chỉ có quyền xem hồ sơ bệnh án, không có thẩm quyền sửa đổi chẩn đoán y khoa và phác đồ điều trị! Thao tác sửa bệnh án chỉ dành cho Bác sĩ điều trị hoặc Quản trị viên.
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['created'])): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            <strong>Thành công:</strong> Hồ sơ bệnh án điện tử mới đã được lập và ghi nhận vào hệ thống thành công!
          </div>
        <?php elseif (isset($_GET['updated'])): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            <strong>Thành công:</strong> Thông tin hồ sơ bệnh án đã được cập nhật thành công!
          </div>
        <?php endif; ?>

        <?php if ($leakActive): ?>
          <div class="leak-box">
            <div style="font-weight: 800; color: #be123c; font-size: 15px; margin-bottom: 6px;">
              [CẢNH BÁO VI PHẠM BẢO MẬT] PHÁT HIỆN LỖ HỔNG IDOR / BOLA (CWE-639) ĐANG BỊ KHAI THÁC!
            </div>
            <p style="color: #9f1239; font-size: 13px;">
              Tài khoản hiện tại: <strong><?= htmlspecialchars($currentUser['username']) ?></strong> (<?= htmlspecialchars($currentUser['full_name']) ?>)<br>
              Chủ sở hữu thực sự của bệnh án: <strong><?= htmlspecialchars($recordDetail['owner_username']) ?></strong> (Bệnh nhân Trần Thị Bình)<br>
              <em>Máy chủ đã bỏ qua kiểm tra quan hệ sở hữu đối tượng và làm rò rỉ dữ liệu y tế nhạy cảm bên dưới!</em>
            </p>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title"><?= htmlspecialchars($recordDetail['title']) ?></div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Mã hồ sơ: #<?= $recordDetail['id'] ?> | Khoa: <?= htmlspecialchars($recordDetail['department']) ?> | Ngày lập: <?= $recordDetail['created_at'] ?>
              </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
              <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
                <?php
                  $canDelCurRec = ($currentUser['role'] === 'admin')
                    || (($recordDetail['doctor_username'] ?? '') === $currentUser['username'])
                    || (($recordDetail['department'] ?? '') === ($currentUser['department'] ?? ''));
                ?>
                <a href="/?view=edit_record&id=<?= $recordDetail['id'] ?>" class="btn btn-primary" style="font-size: 12px;">+ Chỉnh Sửa Bệnh Án</a>
                <?php if ($canDelCurRec): ?>
                  <a href="/?action=delete_record&id=<?= $recordDetail['id'] ?>"
                     onclick="return confirmWithHold(event, { title: 'Xác Nhận Xoá Bệnh Án', message: 'Bạn có chắc chắn muốn xoá vĩnh viễn hồ sơ bệnh án #<?= $recordDetail['id'] ?> (<?= htmlspecialchars($recordDetail['title']) ?>)? Hành động này không thể hoàn tác!' })"
                     class="btn btn-danger"
                     style="font-size: 12px; text-decoration: none;">Xóa Bệnh Án</a>
                <?php endif; ?>
              <?php else: ?>
                <span style="font-size: 11px; font-weight: 700; background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border);">CHẾ ĐỘ CHỈ XEM (BỆNH NHÂN)</span>
              <?php endif; ?>
              <a href="/?view=records" class="btn btn-outline" style="font-size: 12px;">Quay lại danh sách</a>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; font-size: 13px;">
            <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid var(--border);">
              <strong>Bệnh nhân sở hữu:</strong> <code><?= htmlspecialchars($recordDetail['owner_username']) ?></code><br>
              <strong>Bác sĩ phụ trách:</strong> <?= htmlspecialchars($recordDetail['doctor_name'] ?? 'BS. CKI Lê Hoàng Minh') ?>
            </div>
            <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid var(--border);">
              <strong>Chẩn đoán lâm sàng:</strong><br>
              <span style="color: #0369a1; font-weight: 600;"><?= htmlspecialchars($recordDetail['diagnosis'] ?? 'Đang cập nhật') ?></span>
            </div>
          </div>

          <div style="background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 18px; margin-bottom: 20px;">
            <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 8px;">NỘI DUNG BỆNH ÁN & PHÁC ĐỒ ĐIỀU TRỊ:</h3>
            <p style="font-size: 14px; color: #0f172a; white-space: pre-wrap;"><?= htmlspecialchars($recordDetail['content']) ?></p>
          </div>
        </div>

