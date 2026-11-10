        <?php if ($leakActive): ?>
          <div class="leak-box">
            <div style="font-weight: 800; color: #be123c; font-size: 15px; margin-bottom: 6px;">
              [CẢNH BÁO BẢO MẬT] BẠN ĐANG TRUY CẬP CHỈNH SỬA BỆNH ÁN CỦA NGƯỜI KHÁC QUA LỖ HỔNG IDOR!
            </div>
            <p style="color: #9f1239; font-size: 13px;">
              Bệnh nhân An (<code>bn_an</code>) đang chỉnh sửa hồ sơ của Bệnh nhân Bình (<code>bn_binh</code>).
              Khi nhấn "Lưu Thay Đổi Bệnh Án", máy chủ sẽ ghi đè dữ liệu bệnh án trái phép do thiếu kiểm tra quyền sở hữu!
            </p>
          </div>
        <?php endif; ?>

        <div class="card" style="max-width: 680px; margin: 0 auto;">
          <div class="card-head">
            <div>
              <div class="card-title">Chỉnh Sửa Hồ Sơ Bệnh Án #<?= $editRecordDetail['id'] ?></div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Bệnh nhân sở hữu: <code><?= htmlspecialchars($editRecordDetail['owner_username']) ?></code>
              </div>
            </div>
            <a href="/?view=record&id=<?= $editRecordDetail['id'] ?>" class="btn btn-outline">Hủy bỏ</a>
          </div>

          <?php if (isset($_GET['err']) && $_GET['err'] === 'missing_fields'): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin: 0 0 18px 0;">
              <strong>Lỗi:</strong> Tiêu đề và nội dung bệnh án không được để trống!
            </div>
          <?php endif; ?>

          <form action="/?action=edit_record_submit" method="POST" onsubmit="return confirmFormWithHold(event, this, { title: 'Xác Nhận Lưu Bệnh Án', message: 'Bạn có chắc chắn muốn ghi đè nội dung và phác đồ điều trị cho hồ sơ bệnh án #<?= $editRecordDetail['id'] ?>?' })">
            <input type="hidden" name="id" value="<?= $editRecordDetail['id'] ?>">

            <div class="form-group">
              <label>Tiêu đề bệnh án:</label>
              <input type="text" name="title" value="<?= htmlspecialchars($editRecordDetail['title']) ?>" required>
            </div>

            <div class="form-group">
              <label>Chẩn đoán bệnh lý:</label>
              <input type="text" name="diagnosis" value="<?= htmlspecialchars($editRecordDetail['diagnosis'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label>Nội dung bệnh án & phác đồ:</label>
              <textarea name="content" rows="6" required><?= htmlspecialchars($editRecordDetail['content']) ?></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
              <a href="/?view=record&id=<?= $editRecordDetail['id'] ?>" class="btn btn-outline">Hủy Bỏ</a>
              <button type="submit" class="btn btn-primary">Lưu Thay Đổi Bệnh Án</button>
            </div>
          </form>
        </div>

