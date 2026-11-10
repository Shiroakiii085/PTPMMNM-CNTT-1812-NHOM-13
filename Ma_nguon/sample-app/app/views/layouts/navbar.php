  <!-- =======================================================================
       BỘ HEADER VÀ NAVIGATION ĐIỀU KIỆN
       ======================================================================= -->
  <?php if ($view === 'landing'): ?>
    <!-- 1. TOPBAR TIỆN ÍCH Y TẾ BỆNH VIỆN BẠCH MAI -->
    <div class="bm-topbar">
      <div class="bm-topbar-inner">
        <div class="bm-topbar-left">
          <div class="bm-topbar-item">
            
            <span>Tổng đài đặt khám: <strong><a href="tel:1900888866">1900.888.866</a></strong></span>
          </div>
          <div class="bm-topbar-item">
            <span class="bm-topbar-badge">CẤP CỨU 24/7</span>
            <span>Trung tâm Cấp cứu A9: <strong><a href="tel:02438693731">024.3869.3731</a></strong></span>
          </div>
          <div class="bm-topbar-item">
            
            <span>Số 78 Giải Phóng, P. Phương Mai, Q. Đống Đa, TP. Hà Nội</span>
          </div>
        </div>
        <div class="bm-topbar-right">
          <div class="bm-topbar-item">
            <span>Tiếp đón: <strong>05:00 - 16:30 (Thứ 2 - CN)</strong></span>
          </div>
          <?php if ($currentUser): ?>
            <div class="bm-topbar-item">
              <span>Xin chào, <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong></span>
              <a href="/?view=records" style="margin-left: 6px; color: #38bdf8; text-decoration: underline;">[Bệnh Án]</a>
              <a href="/?action=logout" style="margin-left: 6px; color: #f87171;">[Đăng xuất]</a>
            </div>
          <?php else: ?>
            <div class="bm-topbar-item">
              <a href="/?view=login" style="color: #e2e8f0; font-weight: 600;">Cán Bộ Đăng Nhập</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 2. MAIN HEADER THƯƠNG HIỆU BỆNH VIỆN BẠCH MAI -->
    <div class="bm-header">
      <div class="bm-header-inner">
        <a href="/?view=landing" class="bm-brand">
          <div class="bm-logo-emblem">
            <div class="bm-logo-cross"></div>
          </div>
          <div class="bm-brand-text">
            <span class="bm-brand-sub">BỘ Y TẾ</span>
            <span class="bm-brand-title">BỆNH VIỆN BẠCH MAI</span>
            <span class="bm-brand-desc">Bệnh viện Đa khoa hoàn chỉnh hạng Đặc biệt đầu tiên tại Việt Nam</span>
          </div>
        </a>
        <div class="bm-header-actions">
          <div class="bm-header-hotline">
            <span class="bm-hotline-label">Cấp Cứu A9 Tuyến Cuối</span>
            <a href="tel:02438693731" class="bm-hotline-phone">024.3869.3731</a>
          </div>
          <button onclick="openBookingModal()" class="bm-btn-booking">
            ĐẶT LỊCH KHÁM
          </button>
          <a href="/?view=records" class="bm-btn-emr">
            HỒ SƠ BỆNH ÁN EMR
          </a>
        </div>
      </div>
    </div>

    <!-- 3. MAIN NAVIGATION MENU (CHUẨN BACHMAI.GOV.VN) -->
    <nav class="bm-nav">
      <div class="bm-nav-inner">
        <ul class="bm-nav-links">
          <li class="bm-nav-item"><a href="/?view=landing" class="bm-nav-link active">TRANG CHỦ</a></li>
          <li class="bm-nav-item"><a href="#gioi-thieu" class="bm-nav-link">GIỚI THIỆU</a></li>
          <li class="bm-nav-item"><a href="#co-cau" class="bm-nav-link">CƠ CẤU TỔ CHỨC</a></li>
          <li class="bm-nav-item"><a href="#chuyen-khoa" class="bm-nav-link">ĐƠN VỊ CHUYÊN KHOA</a></li>
          <li class="bm-nav-item"><a href="#chuyen-gia" class="bm-nav-link">ĐỘI NGŨ BÁC SĨ</a></li>
          <li class="bm-nav-item"><a href="#danh-cho-nguoi-benh" class="bm-nav-link">DÀNH CHO NGƯỜI BỆNH</a></li>
          <li class="bm-nav-item">
            <a href="/?view=records" class="bm-nav-link">
              BỆNH ÁN ĐIỆN TỬ <span class="bm-nav-badge">EMR</span>
            </a>
          </li>

          <li class="bm-nav-item"><a href="#tin-tuc" class="bm-nav-link">TIN TỨC & SỰ KIỆN</a></li>
          <li class="bm-nav-item"><a href="#lien-he" class="bm-nav-link">LIÊN HỆ</a></li>
        </ul>
        <div style="flex-shrink: 0; margin-left: 14px;">
          <?php if ($currentUser): ?>
            <a href="/?view=records" style="color: #fff; font-size: 13px; text-decoration: none; font-weight: 700; background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
              <?= htmlspecialchars($currentUser['username']) ?> (<?= htmlspecialchars($currentUser['role']) ?>)
            </a>
          <?php else: ?>
            <a href="/?view=login" style="color: #fff; font-size: 13px; text-decoration: none; font-weight: 700; background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
              Đăng Nhập
            </a>
          <?php endif; ?>
        </div>
      </div>
    </nav>

  <?php else: ?>
    <!-- NAVBAR NỘI BỘ CHO CÁC PHÂN HỆ EMR -->
    <header>
      <!-- CỘT TRÁI: LOGO BỆNH VIỆN -->
      <div class="brand">
        <a href="/?view=landing" style="text-decoration: none;">
          <span class="brand-title">MEDIRECORD <span>EMR</span></span>
        </a>
      </div>

      <!-- Nút điều khiển Menu & Chuyển tài khoản trên Mobile -->
      <div class="mobile-nav-toggle-wrap">
        <button type="button" onclick="toggleAccountDrawer()" class="mobile-user-btn" title="Chuyển tài khoản nhanh">
          <?= $currentUser ? htmlspecialchars(function_exists('mb_substr') ? mb_substr($currentUser['full_name'] ?? $currentUser['username'], 0, 2, 'UTF-8') : substr($currentUser['full_name'] ?? $currentUser['username'], 0, 2)) : 'Khách' ?>
        </button>
        <button type="button" class="mobile-nav-btn" onclick="toggleMobileNav()" aria-label="Mở menu" id="mobileNavBtn">
          <span class="nav-bar-icon"></span>
        </button>
      </div>

      <!-- CỘT GIỮA: CÁC NÚT ĐIỀU HƯỚNG CÂN ĐỐI RA TRUNG TÂM -->
      <nav class="nav-links" id="mainNavLinks">
        <a href="/?view=landing" class="<?= $view === 'landing' ? 'active' : '' ?>">Trang Chủ BV</a>
        <a href="/?view=records" class="<?= $view === 'records' ? 'active' : '' ?>"><?= ($currentUser && $currentUser['role'] === 'member') ? 'Bệnh Án Của Tôi' : 'Danh Sách Bệnh Án' ?></a>

        <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
          <a href="/?view=create_record" class="<?= $view === 'create_record' ? 'active' : '' ?>">Lập Bệnh Án</a>
        <?php endif; ?>
        <a href="/?view=announcements" class="<?= $view === 'announcements' ? 'active' : '' ?>">Thông Báo Chung</a>
        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
          <a href="/?view=users" class="<?= $view === 'users' ? 'active' : '' ?>">Quản Trị Người Dùng</a>
        <?php endif; ?>
        <?php if ($currentUser && in_array($currentUser['role'], ['auditor', 'admin'], true)): ?>
          <a href="/?view=tester" class="<?= $view === 'tester' ? 'active' : '' ?>" style="color: var(--primary); font-weight: 700; display: inline-flex; align-items: center;">
            Giám Sát An Ninh & Kiểm Thử
            <?php if ($incidentCount > 0): ?>
              <span class="nav-alert-badge"><?= $incidentCount ?> vi phạm</span>
            <?php endif; ?>
          </a>
        <?php endif; ?>

        <!-- Bảng điều khiển Mobile (chỉ hiển thị khi menu mobile mở) -->
        <div class="mobile-nav-user-panel">
          <?php if ($currentUser): ?>
            <a href="/?view=profile" class="mobile-nav-profile-link">
              <span>Hồ Sơ Cá Nhân & Đổi Mật Khẩu</span>
              <code><?= htmlspecialchars($currentUser['username']) ?></code>
            </a>
            <a href="/?action=logout" class="btn btn-outline" style="width: 100%; text-align: center; color: #dc2626; border-color: #fca5a5;">Đăng Xuất</a>
          <?php else: ?>
            <div style="display: flex; gap: 8px;">
              <a href="/?view=login" class="btn btn-outline" style="flex: 1; text-align: center;">Đăng nhập</a>
              <a href="/?view=register" class="btn btn-primary" style="flex: 1; text-align: center;">Đăng ký</a>
            </div>
          <?php endif; ?>
        </div>
      </nav>

      <!-- CỘT PHẢI: KHU VỰC TÀI KHOẢN VỚI DROPDOWN MENU -->
      <div class="nav-user-dropdown-wrap">
        <?php if ($currentUser): ?>
          <?php
            $userInitials = function_exists('mb_substr') ? mb_substr($currentUser['full_name'] ?? $currentUser['username'], 0, 2, 'UTF-8') : substr($currentUser['full_name'] ?? $currentUser['username'], 0, 2);
            $roleColor = match($currentUser['role'] ?? '') {
              'admin' => '#7c3aed',
              'doctor' => '#0284c7',
              'auditor' => '#d97706',
              default => '#059669',
            };
            $roleLabels = [
              'admin' => 'Quản Trị Viên',
              'doctor' => 'Bác Sĩ Điều Trị',
              'auditor' => 'Kỹ Sư An Ninh',
              'member' => 'Bệnh Nhân',
            ];
            $roleLabel = $roleLabels[$currentUser['role']] ?? strtoupper($currentUser['role']);
          ?>
          <button type="button" class="nav-user-btn <?= $view === 'profile' ? 'active' : '' ?>" id="userDropdownBtn" onclick="toggleUserDropdown(event)" aria-expanded="false" title="Menu tài khoản (<?= htmlspecialchars($currentUser['full_name']) ?>)">
            <div class="nav-avatar-circle" style="background: <?= $roleColor ?>;">
              <?= htmlspecialchars($userInitials) ?>
            </div>
            <div class="nav-user-info">
              <span class="nav-user-name">
                <?= htmlspecialchars($currentUser['full_name']) ?>
              </span>
              <span class="nav-user-role-tag">
                <code><?= htmlspecialchars($currentUser['role']) ?></code>
              </span>
            </div>
            <svg class="dropdown-chevron-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </button>

          <!-- DROPDOWN MENU NỔI -->
          <div class="user-dropdown-menu" id="userDropdownMenu" style="display: none;">
            <div class="user-dropdown-header">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div class="nav-avatar-circle" style="background: <?= $roleColor ?>; width: 38px; height: 38px; font-size: 13px;">
                  <?= htmlspecialchars($userInitials) ?>
                </div>
                <div style="flex: 1; min-width: 0;">
                  <div style="font-weight: 700; font-size: 13.5px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?= htmlspecialchars($currentUser['full_name']) ?>
                  </div>
                  <div style="font-size: 11.5px; color: #64748b; font-family: monospace;">
                    @<?= htmlspecialchars($currentUser['username']) ?>
                  </div>
                </div>
              </div>
              <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                <span class="user-dropdown-badge" style="background: <?= $roleColor ?>18; color: <?= $roleColor ?>; border: 1px solid <?= $roleColor ?>30;">
                  <?= htmlspecialchars($roleLabel) ?>
                </span>
                <span style="font-size: 11px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px;">
                  <?= htmlspecialchars($currentUser['department'] ?? 'Khoa Khám Bệnh') ?>
                </span>
              </div>
            </div>

            <div class="user-dropdown-body">
              <a href="/?view=profile" class="user-dropdown-item <?= $view === 'profile' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <div style="display: flex; flex-direction: column;">
                  <span style="font-weight: 600; font-size: 13px; color: #0f172a;">Trang Cá Nhân</span>
                  <span style="font-size: 11px; color: #64748b;">Xem thông tin & đổi mật khẩu</span>
                </div>
              </a>


              <div class="user-dropdown-divider"></div>

              <a href="/?action=logout" class="user-dropdown-item item-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                  <polyline points="16 17 21 12 16 7"></polyline>
                  <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span style="font-weight: 600; font-size: 13px;">Đăng Xuất</span>
              </a>
            </div>
          </div>
        <?php else: ?>
          <div style="display: flex; gap: 8px; align-items: center;">
            <a href="/?view=login" class="btn btn-outline" style="padding: 8px 14px; font-size: 13px;">Đăng nhập</a>
            <a href="/?view=register" class="btn btn-primary" style="padding: 8px 14px; font-size: 13px;">Đăng ký</a>
          </div>
        <?php endif; ?>
      </div>
    </header>
  <?php endif; ?>

