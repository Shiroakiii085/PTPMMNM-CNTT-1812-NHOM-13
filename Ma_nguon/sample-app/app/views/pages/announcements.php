        <?php
          $isStaff = ($currentUser && in_array($currentUser['role'], ['admin', 'doctor', 'auditor'], true));
          $annTab = $_GET['tab'] ?? 'patient';
          if ($annTab === 'internal' && !$isStaff) {
              $annTab = 'patient';
              $unauthTabWarning = true;
          } else {
              $unauthTabWarning = false;
          }

          $patientAnnouncements = array_values(array_filter($mockAnnouncements, fn($a) => ($a['audience'] ?? 'patient') === 'patient'));
          $internalAnnouncements = $isStaff ? array_values(array_filter($mockAnnouncements, fn($a) => ($a['audience'] ?? 'patient') === 'internal')) : [];

          $displayAnnouncements = ($annTab === 'internal' && $isStaff) ? $internalAnnouncements : $patientAnnouncements;
        ?>

        <?php if ($unauthTabWarning || (isset($_GET['err']) && $_GET['err'] === 'unauthorized_internal_announcements')): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
            <strong>Từ chối truy cập:</strong> Bạn không có quyền truy cập chuyên mục Thông Báo Nội Bộ của Cán Báo Y Tế theo chính sách phân quyền RBAC!
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg'])): ?>
          <?php if ($_GET['msg'] === 'announcement_added'): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
              <strong>Thành công:</strong> Thông báo bệnh viện mới đã được đăng tải thành công!
            </div>
          <?php elseif ($_GET['msg'] === 'announcement_updated'): ?>
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
              <strong>Cập nhật:</strong> Thông tin bản tin thông báo đã được lưu thay đổi thành công!
            </div>
          <?php elseif ($_GET['msg'] === 'announcement_deleted'): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
              <strong>Đã xóa:</strong> Bản tin thông báo đã được gỡ bỏ khỏi hệ thống!
            </div>
          <?php endif; ?>
        <?php elseif (isset($_GET['err'])): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
            <?php if ($_GET['err'] === 'missing_fields'): ?>
              <strong>Lỗi:</strong> Tiêu đề và nội dung thông báo không được để trống!
            <?php elseif ($_GET['err'] === 'invalid_id'): ?>
              <strong>Lỗi:</strong> Mã thông báo không hợp lệ hoặc không tồn tại!
            <?php else: ?>
              <strong>Lỗi:</strong> <?= htmlspecialchars($_GET['err']) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- THANH ĐIỀU HƯỚNG TAB PHÂN QUYỀN (RBAC TABS) -->
        <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; align-items: center;">
          <a href="/?view=announcements&tab=patient" class="btn <?= $annTab === 'patient' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 13px; padding: 8px 16px; text-decoration: none;">
            Thông Báo Cho Bệnh Nhân (<?= count($patientAnnouncements) ?>)
          </a>
          <?php if ($isStaff): ?>
            <a href="/?view=announcements&tab=internal" class="btn <?= $annTab === 'internal' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 13px; padding: 8px 16px; text-decoration: none; border-color: #f59e0b; <?= $annTab === 'internal' ? 'background: #d97706; color: #fff;' : 'color: #b45309;' ?>">
              Thông Báo Nội Bộ Cán Bộ Y Tế (<?= count($internalAnnouncements) ?>) <span style="font-size: 10px; background: rgba(0,0,0,0.12); padding: 2px 6px; border-radius: 10px; margin-left: 4px;">NỘI BỘ</span>
            </a>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">
                <?= $annTab === 'internal' ? 'Bản Tin & Thông Báo Nội Bộ Bệnh Viện' : 'Thông Báo Dành Cho Bệnh Nhân & Thân Nhân' ?>
              </div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                <?= $annTab === 'internal' ? 'Phạm vi: Chỉ lưu hành nội bộ y bác sĩ, ban giám đốc và kỹ sư kiểm toán EMR' : 'Phạm vi: Thông báo dùng chung công khai cho toàn thể người bệnh và thân nhân' ?>
              </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
              <span style="font-size: 12px; color: var(--muted);"><?= count($displayAnnouncements) ?> thông báo</span>
              <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                <button onclick="openAddAnnouncementModal('<?= $annTab ?>')" class="btn btn-primary" style="font-size: 12px;">+ Đăng Thông Báo Mới</button>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($annTab === 'internal'): ?>
            <div style="background: #fffbeb; border-bottom: 1px solid #fde68a; padding: 12px 20px; font-size: 12px; color: #92400e; display: flex; align-items: center; justify-content: space-between;">
              <div>
                <strong>LƯU HÀNH NỘI BỘ:</strong> Chuyên mục dành riêng cho cán bộ y tế (Bác sĩ, Điều dưỡng, Kỹ sư kiểm toán, Quản trị viên). Bệnh nhân và khách vãng lai bị chặn truy cập theo chính sách RBAC.
              </div>
              <span style="font-size: 11px; font-weight: 700; background: #fef3c7; color: #b45309; padding: 3px 8px; border-radius: 4px; border: 1px solid #fcd34d; white-space: nowrap;">BẢO MẬT NỘI BỘ</span>
            </div>
          <?php endif; ?>

          <div id="announcementsList" style="padding: 16px 20px;">
            <?php if (empty($displayAnnouncements)): ?>
              <div style="text-align: center; padding: 40px; color: var(--muted);">
                <?= $annTab === 'internal' ? 'Hiện chưa có thông báo nội bộ mới nào dành cho cán bộ y tế.' : 'Hiện chưa có thông báo mới nào từ bệnh viện dành cho người bệnh.' ?>
              </div>
            <?php else: ?>
              <?php foreach ($displayAnnouncements as $ann): ?>
                <?php $isAudInternal = (($ann['audience'] ?? 'patient') === 'internal'); ?>
                <div class="announcement-card" data-pageable="true" style="padding: 16px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 14px; background: #fff;">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <h4 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;"><?= htmlspecialchars($ann['title']) ?></h4>
                      <?php if ($isAudInternal): ?>
                        <span style="font-size: 10px; font-weight: 700; background: #fef3c7; color: #b45309; padding: 2px 6px; border-radius: 4px; border: 1px solid #fde68a;">NỘI BỘ Y TẾ</span>
                      <?php else: ?>
                        <span style="font-size: 10px; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; border: 1px solid #bae6fd;">CHO BỆNH NHÂN</span>
                      <?php endif; ?>
                    </div>
                    <span style="font-size: 11px; color: #64748b;"><?= $ann['created_at'] ?></span>
                  </div>
                  <div style="font-size: 13px; color: #475569; line-height: 1.6; margin-bottom: 8px;">
                    <?= nl2br(htmlspecialchars($ann['body'] ?? $ann['content'] ?? '')) ?>
                  </div>
                  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; padding-top: 8px; border-top: 1px dashed var(--border);">
                    <div style="font-size: 11px; color: #94a3b8;">
                      Người đăng: <strong><?= htmlspecialchars($ann['author'] ?? 'Ban Giám Đốc Bệnh Viện') ?></strong> | Đối tượng: <code><?= $isAudInternal ? 'Nội bộ cán bộ NV y tế' : 'Bệnh nhân & Thân nhân' ?></code>
                    </div>
                    <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                      <div style="display: inline-flex; gap: 6px;">
                        <button type="button" onclick="openEditAnnouncementModal(<?= (int)$ann['id'] ?>, <?= htmlspecialchars(json_encode($ann['title']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($ann['body'] ?? $ann['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, '<?= $ann['audience'] ?? 'patient' ?>')" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;">Sửa</button>
                        <a href="/?action=delete_announcement&id=<?= (int)$ann['id'] ?>" onclick="return confirmWithHold(event, { title: 'Xác Nhận Xoá Thông Báo', message: 'Bạn có chắc chắn muốn xóa thông báo này? Dữ liệu thông báo sẽ bị loại bỏ khỏi bảng tin chung.' })" class="btn btn-danger" style="padding: 3px 8px; font-size: 11px; text-decoration: none;">Xóa</a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div id="announcementsPagination" class="pagination-wrap"></div>
        </div>

        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
          <!-- MODAL THÊM THÔNG BÁO -->
          <div id="addAnnouncementModal" class="modal-overlay" style="display: none;">
            <div class="modal-box">
              <div class="card-head" style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
                <div class="card-title">Đăng Thông Báo Bệnh Viện Mới</div>
                <button type="button" onclick="closeAddAnnouncementModal()" class="btn btn-outline" style="padding: 2px 8px;">&times;</button>
              </div>
              <form action="/?action=add_announcement" method="POST">
                <div class="form-group">
                  <label>Phân loại đối tượng nhận thông báo (Phân quyền): <span style="color:#ef4444;">*</span></label>
                  <select name="audience" id="addAnnouncementAudience" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                    <option value="patient">Thông báo cho Bệnh nhân & Thân nhân (Công khai)</option>
                    <option value="internal">Thông báo Nội bộ cán bộ y tế (Chỉ Bác sĩ, Nhân viên & Quản trị)</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Tiêu đề thông báo: <span style="color:#ef4444;">*</span></label>
                  <input type="text" name="title" placeholder="VD: Kế hoạch khám định kỳ..." required>
                </div>
                <div class="form-group">
                  <label>Nội dung chi tiết: <span style="color:#ef4444;">*</span></label>
                  <textarea name="body" rows="5" placeholder="Nhập nội dung thông báo gửi đến đối tượng tương ứng..." required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 13px; box-sizing: border-box;"></textarea>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                  <button type="button" onclick="closeAddAnnouncementModal()" class="btn btn-outline">Hủy Bỏ</button>
                  <button type="submit" class="btn btn-primary">Đăng Thông Báo</button>
                </div>
              </form>
            </div>
          </div>

          <!-- MODAL SỬA THÔNG BÁO -->
          <div id="editAnnouncementModal" class="modal-overlay" style="display: none;">
            <div class="modal-box">
              <div class="card-head" style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
                <div class="card-title">Chỉnh Sửa Thông Báo Bệnh Viện</div>
                <button type="button" onclick="closeEditAnnouncementModal()" class="btn btn-outline" style="padding: 2px 8px;">&times;</button>
              </div>
              <form action="/?action=edit_announcement" method="POST">
                <input type="hidden" id="editAnnouncementId" name="id">
                <div class="form-group">
                  <label>Phân loại đối tượng nhận thông báo (Phân quyền): <span style="color:#ef4444;">*</span></label>
                  <select name="audience" id="editAnnouncementAudience" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                    <option value="patient">Thông báo cho Bệnh nhân & Thân nhân (Công khai)</option>
                    <option value="internal">Thông báo Nội bộ cán bộ y tế (Chỉ Bác sĩ, Nhân viên & Quản trị)</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Tiêu đề thông báo: <span style="color:#ef4444;">*</span></label>
                  <input type="text" id="editAnnouncementTitle" name="title" required>
                </div>
                <div class="form-group">
                  <label>Nội dung chi tiết: <span style="color:#ef4444;">*</span></label>
                  <textarea id="editAnnouncementBody" name="body" rows="5" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 13px; box-sizing: border-box;"></textarea>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                  <button type="button" onclick="closeEditAnnouncementModal()" class="btn btn-outline">Hủy Bỏ</button>
                  <button type="submit" class="btn btn-primary">Lưu Thay Đổi</button>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>

