  <!-- =======================================================================
       CÁC NÚT TRÒN NỔI (FAB) & CỬA SỔ ĐIỀU KHIỂN (HIỂN THỊ TOÀN HỆ THỐNG)
       ======================================================================= -->
  <!-- MODEL 3D FLOATING ROBOT Ở GÓC DƯỚI CÙNG BÊN PHẢI -->
  <div id="aiFab" onclick="toggleAiChat()" class="ai-fab-3d-trigger" title="Nhấn để mở Trợ lý AI MediBot">
    <div id="aiFabAvatar" class="robot-head-container fab-robot" style="width: 74px; height: 74px; display: flex; align-items: center; justify-content: center; overflow: visible;"></div>
  </div>

  <!-- CỬA SỔ CHATBOT AI TRỢ LÝ MEDIRECORD -->
  <div id="aiChatDrawer" class="ai-chat-drawer" style="display: none;">
    <div class="ai-drawer-head">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div id="aiHeaderAvatar" class="robot-head-container header-robot" style="width: 46px; height: 46px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: visible;"></div>
        <div>
          <span style="font-size: 15px; font-weight: 800; color: #fff; letter-spacing: 0.3px;">MEDIBOT</span>
        </div>
      </div>
      <div style="display: flex; gap: 6px; align-items: center;">
        <button onclick="resetAiChat()" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: #bae6fd; border-color: rgba(186,230,253,0.35); background: transparent;" title="Xóa lịch sử và bắt đầu cuộc trò chuyện mới">Chat Mới</button>
        <button onclick="toggleAiChat()" class="btn btn-outline" style="padding: 3px 10px; font-size: 11px; color: #e0e7ff; border-color: rgba(255,255,255,0.3); background: transparent;">Đóng</button>
      </div>
    </div>

    <!-- Danh sách tin nhắn trò chuyện -->
    <div id="aiChatMessages" class="ai-chat-body">
    </div>

    <!-- Gợi ý câu hỏi nhanh chuẩn hoá (Quick Chips) -->
    <div class="ai-quick-prompts" id="aiQuickPrompts">
      <?php if ($currentUser && $currentUser['role'] === 'member'): ?>
        <button onclick="sendQuickPrompt('Tôi có những hồ sơ bệnh án nào trên hệ thống?')" class="ai-chip">Bệnh án của tôi</button>
        <button onclick="sendQuickPrompt('Tóm tắt tình trạng sức khỏe và đơn thuốc điều trị của tôi')" class="ai-chip">Tóm tắt đơn thuốc</button>
        <button onclick="sendQuickPrompt('Tôi có thể xem hồ sơ bệnh án của bệnh nhân khác không?')" class="ai-chip">Xem bệnh án người khác</button>
        <button onclick="sendQuickPrompt('Tư vấn chế độ dinh dưỡng và lối sống phục hồi sức khỏe')" class="ai-chip">Tư vấn sức khỏe</button>
        <button onclick="sendQuickPrompt('Lỗ hổng IDOR trong bệnh án điện tử là gì và ảnh hưởng ra sao?')" class="ai-chip">Lỗ hổng IDOR là gì?</button>
      <?php elseif ($currentUser && $currentUser['role'] === 'doctor'): ?>
        <button onclick="sendQuickPrompt('Quy trình tạo và gửi hồ sơ bệnh án mới cho bệnh nhân thế nào?')" class="ai-chip">Cách tạo bệnh án mới</button>
        <button onclick="sendQuickPrompt('Quy định phân quyền bệnh án theo chuyên khoa và khoa phòng điều trị?')" class="ai-chip">Phân quyền theo khoa</button>
        <button onclick="sendQuickPrompt('Hướng dẫn cách chỉnh sửa hoặc xóa hồ sơ bệnh án trên hệ thống?')" class="ai-chip">Sửa & xóa bệnh án</button>
        <button onclick="sendQuickPrompt('Gợi ý phác đồ chẩn đoán lâm sàng và nguyên tắc kê đơn thuốc an toàn')" class="ai-chip">Phác đồ chẩn đoán</button>
        <button onclick="sendQuickPrompt('Giải thích cơ chế phân quyền bảo mật ABAC trong hệ thống EMR')" class="ai-chip">Cơ chế bảo mật ABAC</button>
      <?php elseif ($currentUser && $currentUser['role'] === 'admin'): ?>
        <button onclick="sendQuickPrompt('Hướng dẫn quản lý tài khoản, thêm mới và phân quyền nhân viên y tế?')" class="ai-chip">Quản trị người dùng</button>
        <button onclick="sendQuickPrompt('Quyền hạn của Quản trị viên đối với hồ sơ bệnh án và phân quyền toàn viện?')" class="ai-chip">Quyền hạn của Admin</button>
        <button onclick="sendQuickPrompt('Hướng dẫn đăng thông báo bệnh viện cho nội bộ và bệnh nhân?')" class="ai-chip">Đăng thông báo BV</button>
        <button onclick="sendQuickPrompt('Cách kích hoạt kiểm thử an ninh IDOR để đánh giá an toàn hệ thống?')" class="ai-chip">Kiểm thử an ninh IDOR</button>
        <button onclick="sendQuickPrompt('Các nguyên tắc bảo vệ dữ liệu y tế và chống rò rỉ thông tin cá nhân?')" class="ai-chip">Bảo vệ dữ liệu y tế</button>
      <?php elseif ($currentUser && $currentUser['role'] === 'auditor'): ?>
        <button onclick="sendQuickPrompt('Cơ chế hoạt động của lỗ hổng IDOR/BOLA (CWE-639) trên hệ thống EMR?')" class="ai-chip">Cơ chế lỗ hổng IDOR</button>
        <button onclick="sendQuickPrompt('Hướng dẫn quy trình kích hoạt kiểm thử và chạy bộ 88 ca test tự động?')" class="ai-chip">Chạy 88 ca kiểm thử</button>
        <button onclick="sendQuickPrompt('So sánh sự khác biệt giữa mô hình phân quyền RBAC và ABAC trong y tế?')" class="ai-chip">So sánh RBAC & ABAC</button>
        <button onclick="sendQuickPrompt('Các giải pháp kỹ thuật khắc phục triệt để lỗ hổng kiểm soát truy cập đối tượng?')" class="ai-chip">Giải pháp khắc phục IDOR</button>
      <?php else: ?>
        <button onclick="sendQuickPrompt('Hệ thống MediRecord EMR có những chức năng chính nào?')" class="ai-chip">Chức năng MediRecord</button>
        <button onclick="sendQuickPrompt('Danh sách các tài khoản thực nghiệm và mật khẩu dùng thử là gì?')" class="ai-chip">Tài khoản thực nghiệm</button>
        <button onclick="sendQuickPrompt('Lỗ hổng bảo mật IDOR trong y tế là gì và tại sao nguy hiểm?')" class="ai-chip">Lỗ hổng IDOR là gì?</button>
        <button onclick="sendQuickPrompt('Tư vấn chế độ ăn uống lành mạnh và phòng ngừa bệnh tim mạch')" class="ai-chip">Lối sống lành mạnh</button>
        <button onclick="sendQuickPrompt('Hướng dẫn cách đăng nhập và sử dụng các tính năng trên website?')" class="ai-chip">Hướng dẫn sử dụng web</button>
      <?php endif; ?>
    </div>

    <!-- Input Form -->
    <div class="ai-chat-input-row">
      <input type="text" id="aiChatInput" placeholder="Hỏi bất kỳ điều gì: bệnh án, sử dụng web, IDOR, lập trình, khoa học..." autocomplete="off">
      <button id="aiSendBtn" onclick="submitAiChat()" class="btn btn-pri" style="background: #6366f1; border-color: #4f46e5; padding: 7px 14px; font-weight: 700; font-size: 12px;">Gửi</button>
    </div>
  </div>

  <!-- NÚT TRÒN NỔI (FAB) CHUYỂN TÀI KHOẢN NHANH (NẰM TRÊN NÚT TERMINAL) -->
  <button id="accountFab" onclick="toggleAccountDrawer()" class="account-fab" title="Chuyển Nhanh Tài Khoản (1-Click Switch)">
    <span class="account-fab-icon" style="font-size: 11px; font-weight: 800; color: #fff;">USER</span>
    <?php if ($incidentCount > 0): ?>
      <span class="account-fab-badge"><?= $incidentCount ?></span>
    <?php endif; ?>
  </button>


  <!-- =======================================================================
       MODAL GIỮ ĐỂ MỞ KHOÁ (HOLD TO UNLOCK CONFIRMATION DIAL)
       ======================================================================= -->
  <div id="holdConfirmModal" class="modal-overlay" style="display: none; z-index: 100000; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);">
    <div class="modal-box" style="max-width: 440px; text-align: center; padding: 28px 24px; border-radius: 16px; box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);">
      <div style="font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--primary); margin-bottom: 6px;" id="holdModalBadge">XÁC NHẬN BẢO MẬT</div>
      <h3 style="margin: 0 0 8px 0; font-size: 19px; font-weight: 800; color: #0f172a;" id="holdModalTitle">Giữ để mở khoá xác nhận</h3>
      <p style="margin: 0 0 24px 0; font-size: 13px; color: #64748b; line-height: 1.5;" id="holdModalDesc">
        Vui lòng nhấn và giữ biểu tượng ổ khóa bên dưới trong 1 giây để hoàn tất thao tác quan trọng này.
      </p>

      <div class="lp-lock" id="globalHoldLock">
        <div class="lp-dial">
          <svg class="lp-ring" width="160" height="160" viewBox="0 0 160 160" aria-hidden="true">
            <circle class="lp-track" cx="80" cy="80" r="72" />
            <circle class="lp-progress" id="holdLockProgress" cx="80" cy="80" r="72" />
          </svg>
          <button type="button" class="lp-button" id="holdLockButton" aria-label="Hold to unlock">
            <svg class="lp-icon" width="40" height="40" viewBox="0 0 24 24" aria-hidden="true">
              <path class="lp-shackle" id="holdLockShackle" d="M8 11V7.5a4 4 0 0 1 8 0V11" />
              <rect class="lp-body" x="5" y="11" width="14" height="10" rx="2.5" />
              <circle class="lp-keyhole" cx="12" cy="16" r="1.25" />
            </svg>
          </button>
        </div>
        <p class="lp-label" id="holdLockLabel" aria-live="polite">Nhấn giữ để xác nhận</p>
      </div>

      <div style="margin-top: 24px; display: flex; justify-content: center; gap: 10px;">
        <button type="button" class="btn btn-outline" onclick="closeHoldConfirmModal()" style="padding: 9px 22px; font-size: 13px; font-weight: 600;">Hủy Bỏ</button>
      </div>
    </div>
  </div>

  <!-- POPUP CHUYỂN TÀI KHOẢN NHANH -->
  <div id="accountDrawer" class="account-drawer" style="display: none;">
    <div class="acc-drawer-head">
      <div>
        <h4 style="margin: 0; font-size: 13px; font-weight: 700; color: #fff;">CHUYỂN NHANH VAI TRÒ (1-CLICK)</h4>
        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
          <?= $currentUser ? 'Đang dùng: <strong>' . htmlspecialchars($currentUser['username']) . '</strong>' : 'Khách vãng lai' ?>
        </div>
      </div>
      <button onclick="toggleAccountDrawer()" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: #cbd5e1; border-color: #475569; background: transparent;">Đóng</button>
    </div>

    <?php if ($currentUser): ?>
      <div style="padding: 10px 14px; background: #1e293b; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 12px; color: #cbd5e1;">Quản lý tài khoản:</span>
        <a href="/?view=profile" class="btn btn-outline" style="padding: 4px 10px; font-size: 11px; background: var(--primary); border-color: var(--primary); color: #fff; text-decoration: none; font-weight: 700;">Trang Cá Nhân & Đổi MK</a>
      </div>
    <?php endif; ?>

    <div class="acc-drawer-list">
      <!-- Admin -->
      <a href="/?action=switch_user&user=admin_bv" class="acc-item <?= ($currentUser && $currentUser['username'] === 'admin_bv') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-adm">AD</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Quản trị viên Bệnh viện Bạch Mai</div>
            <div style="font-size: 11px; color: #64748b;">admin_bv - Phòng CNTT & An Ninh Mạng</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'admin_bv'): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Doctor -->
      <a href="/?action=switch_user&user=bs_minh" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bs_minh') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-doc">BS</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">BS. CKI Lê Hoàng Minh</div>
            <div style="font-size: 11px; color: #64748b;">bs_minh - Khoa Tim Mạch & Nội Tiết</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bs_minh'): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Patient An -->
      <a href="/?action=switch_user&user=bn_an" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bn_an') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-pat">AN</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Bệnh nhân Nguyễn Văn An</div>
            <div style="font-size: 11px; color: #64748b;">bn_an - Điều trị Ngoại trú (Tim Mạch)</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bn_an'): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Patient Binh -->
      <a href="/?action=switch_user&user=bn_binh" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bn_binh') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-pat">BI</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Bệnh nhân Trần Thị Bình</div>
            <div style="font-size: 11px; color: #64748b;">bn_binh - Điều trị Nội trú (Khoa Ung Bướu)</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bn_binh'): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Tester -->
      <a href="/?action=switch_user&user=tester_sec" class="acc-item <?= ($currentUser && $currentUser['username'] === 'tester_sec') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-aud">KT</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Kỹ sư Nguyễn Kiểm Thử</div>
            <div style="font-size: 11px; color: #64748b;">tester_sec - Ban An Toàn Thông Tin & Kiểm Toán Y Tế</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
          <?php if ($incidentCount > 0): ?>
            <span style="font-size: 10px; background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 10px; font-weight: 700;"><?= $incidentCount ?> sự cố</span>
          <?php endif; ?>
          <?php if ($currentUser && $currentUser['username'] === 'tester_sec'): ?>
            <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
          <?php endif; ?>
        </div>
      </a>

      <?php
        $defaultUsersList = ['admin_bv', 'bs_minh', 'bn_an', 'bn_binh', 'tester_sec'];
        $extraCustomUsers = array_filter($mockUsers, fn($u, $k) => !in_array($k, $defaultUsersList, true), ARRAY_FILTER_USE_BOTH);
        foreach ($extraCustomUsers as $exU):
          $fullNameStr = $exU['full_name'] ?? $exU['username'];
          $rawSub = function_exists('mb_substr') ? mb_substr($fullNameStr, 0, 2, 'UTF-8') : substr($fullNameStr, 0, 2);
          $exInit = function_exists('mb_strtoupper') ? mb_strtoupper($rawSub, 'UTF-8') : strtoupper($rawSub);
          $isCurrentEx = ($currentUser && $currentUser['username'] === $exU['username']);
      ?>
        <a href="/?action=switch_user&user=<?= urlencode($exU['username']) ?>" class="acc-item <?= $isCurrentEx ? 'active' : '' ?>">
          <div style="display: flex; align-items: center;">
            <div class="acc-initial init-pat" style="background: #e0f2fe; color: #0284c7;"><?= htmlspecialchars($exInit) ?></div>
            <div>
              <div style="font-weight: 700; font-size: 13px; color: #0f172a;"><?= htmlspecialchars($fullNameStr) ?></div>
              <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($exU['username']) ?> - <?= htmlspecialchars($exU['department'] ?? 'Tài khoản tùy chỉnh') ?> (<?= htmlspecialchars($exU['role'] ?? 'member') ?>)</div>
            </div>
          </div>
          <?php if ($isCurrentEx): ?>
            <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <!-- Anonymous / Guest -->
      <a href="/?action=switch_user&user=anon" class="acc-item <?= ($currentUser === null) ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-anon">--</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Khách Vãng Lai</div>
            <div style="font-size: 11px; color: #64748b;">Chưa đăng nhập (Anonymous)</div>
          </div>
        </div>
        <?php if ($currentUser === null): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>
    </div>

    <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
      <a href="/?view=login" style="color: var(--primary); text-decoration: none; font-weight: 700;">Đăng Nhập</a>
      <span style="color: #cbd5e1;">|</span>
      <a href="/?view=register" style="color: var(--primary); text-decoration: none; font-weight: 700;">Đăng Ký</a>
      <span style="color: #cbd5e1;">|</span>
      <a href="/?action=logout" style="color: #ef4444; text-decoration: none; font-weight: 700;">Đăng Xuất</a>
    </div>
  </div>



  <!-- MOBILE BOTTOM APP BAR (Dành riêng cho màn hình di động) -->
  <nav class="mobile-bottom-bar">
    <a href="/?view=landing" class="mobile-tab <?= $view === 'landing' ? 'active' : '' ?>">
      <span>Trang Chủ</span>
    </a>
    <a href="/?view=records" class="mobile-tab <?= $view === 'records' ? 'active' : '' ?>">
      <span>Bệnh Án</span>
    </a>
    <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
      <a href="/?view=create_record" class="mobile-tab <?= $view === 'create_record' ? 'active' : '' ?>">
        <span>Lập Hồ Sơ</span>
      </a>
    <?php else: ?>
      <a href="/?view=announcements" class="mobile-tab <?= $view === 'announcements' ? 'active' : '' ?>">
        <span>Thông Báo</span>
      </a>
    <?php endif; ?>
    <?php if ($currentUser && in_array($currentUser['role'], ['auditor', 'admin'], true)): ?>
      <a href="/?view=tester" class="mobile-tab <?= $view === 'tester' ? 'active' : '' ?>">
        <span>Kiểm Thử</span>
      </a>
    <?php else: ?>
      <button type="button" onclick="toggleAccountDrawer()" class="mobile-tab">
        <span>Đổi TK</span>
      </button>
    <?php endif; ?>
    <?php if ($currentUser): ?>
      <a href="/?view=profile" class="mobile-tab <?= $view === 'profile' ? 'active' : '' ?>">
        <span>Cá Nhân</span>
      </a>
    <?php endif; ?>
    <button type="button" onclick="toggleAiChat()" class="mobile-tab tab-ai">
      <span>Trợ Lý AI</span>
    </button>
  </nav>

