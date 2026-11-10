      <!-- BANNER TRẠNG THÁI BẢO MẬT (Chỉ hiển thị trong nội bộ hệ thống) -->
      <?php if ($bugIdor): ?>
        <div class="idor-banner">
          <div>
            <strong style="color: var(--danger); font-size: 14px;">LỖ HỔNG IDOR / BOLA ĐANG BẬT!</strong>
            <p style="color: #7f1d1d; font-size: 12px; margin-top: 4px;">
              Hệ thống đang bỏ qua kiểm tra quan hệ sở hữu đối tượng. Bệnh nhân An (<code>bn_an</code>) có thể đọc trộm và chỉnh sửa trái phép Bệnh án số 03 của Bình (<code>bn_binh</code>).
            </p>
          </div>
          <div>
            <a href="/?action=toggle_bug_idor&state=0&return_view=<?= urlencode($view) ?>" onclick="return confirmWithHold(event, { title: 'Xác Nhận Vá Lỗi IDOR', message: 'Thao tác này sẽ kích hoạt cơ chế ABAC để đóng hoàn toàn lỗ hổng IDOR, bảo vệ dữ liệu hồ sơ y tế.' })" class="btn btn-safe" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">Vá Lỗ Hổng (Tắt Lỗi)</a>
          </div>
        </div>
      <?php else: ?>
        <div class="idor-banner idor-banner-safe">
          <div>
            <strong style="color: var(--safe); font-size: 14px;">HỆ THỐNG AN TOÀN (ABAC ACTIVE)</strong>
            <p style="color: #14532d; font-size: 12px; margin-top: 4px;">
              Quy tắc kiểm soát thuộc tính sở hữu đang hoạt động: Bệnh nhân chỉ xem và sửa hồ sơ của chính mình. Thao tác truy cập chéo bị chặn với HTTP 403 Forbidden.
            </p>
          </div>
          <div>
            <a href="/?action=toggle_bug_idor&state=1&return_view=<?= urlencode($view) ?>" onclick="return confirmWithHold(event, { title: 'Xác Nhận Bật Lỗi IDOR', message: 'Thao tác này sẽ mở lỗ hổng CWE-639 trên hệ thống, cho phép bệnh nhân truy cập hồ sơ của nhau để kiểm thử an ninh.' })" class="btn btn-danger" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">Tạo Lỗi (Bật IDOR)</a>
          </div>
        </div>
      <?php endif; ?>

