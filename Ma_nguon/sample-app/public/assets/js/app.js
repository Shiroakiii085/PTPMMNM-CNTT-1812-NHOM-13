
    // =========================================================================
    // NAVBAR USER DROPDOWN CONTROLLER
    // =========================================================================
    function toggleUserDropdown(e) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      const menu = document.getElementById('userDropdownMenu');
      const btn = document.getElementById('userDropdownBtn');
      if (!menu) return;

      const isDisplayed = menu.style.display === 'block';
      if (isDisplayed) {
        closeUserDropdown();
      } else {
        menu.style.display = 'block';
        if (btn) {
          btn.classList.add('menu-open');
          btn.setAttribute('aria-expanded', 'true');
        }
      }
    }

    function closeUserDropdown() {
      const menu = document.getElementById('userDropdownMenu');
      const btn = document.getElementById('userDropdownBtn');
      if (menu) menu.style.display = 'none';
      if (btn) {
        btn.classList.remove('menu-open');
        btn.setAttribute('aria-expanded', 'false');
      }
    }

    // Đóng Dropdown khi click ra ngoài hoặc bấm Escape
    document.addEventListener('click', function(e) {
      const wrap = document.querySelector('.nav-user-dropdown-wrap');
      if (wrap && !wrap.contains(e.target)) {
        closeUserDropdown();
      }
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeUserDropdown();
      }
    });

    // Bật/tắt Menu điều hướng trên Mobile
    function toggleMobileNav() {
      const nav = document.getElementById('mainNavLinks');
      const btn = document.getElementById('mobileNavBtn');
      if (!nav) return;
      const isOpen = nav.classList.contains('mobile-open');
      if (isOpen) {
        nav.classList.remove('mobile-open');
        if (btn) btn.classList.remove('is-active');
      } else {
        nav.classList.add('mobile-open');
        if (btn) btn.classList.add('is-active');
      }
    }

    // Tự động đóng menu mobile khi nhấn ra ngoài
    document.addEventListener('click', function(e) {
      const nav = document.getElementById('mainNavLinks');
      const btn = document.getElementById('mobileNavBtn');
      if (!nav || !btn) return;
      if (!nav.contains(e.target) && !btn.contains(e.target) && nav.classList.contains('mobile-open')) {
        nav.classList.remove('mobile-open');
        btn.classList.remove('is-active');
      }
    });

    // Bật/tắt Cửa sổ Chuyển Tài Khoản Nhanh
    function toggleAccountDrawer() {
      const accDrawer = document.getElementById('accountDrawer');
      const aiDrawer = document.getElementById('aiChatDrawer');
      if (!accDrawer) return;
      if (accDrawer.style.display === 'none' || accDrawer.style.display === '') {
        accDrawer.style.display = 'block';
        if (aiDrawer) aiDrawer.style.display = 'none';
      } else {
        accDrawer.style.display = 'none';
      }
    }

    // =========================================================================
    // CHATBOT AI VỚI TYPEWRITER STREAMING (CHATGPT STYLE) & ROBOT-HEADS SYNC
    // =========================================================================
    let aiChatInitialized = false;
    let isAiGenerating = false;
    let aiChatHistory = [];

    // Khởi tạo các model 3D Robot Head (fayazara/robot-heads 3D Canvas)
    function init3DRobotHeads() {
      if (typeof window.mountRobotHead !== 'function') return;

      // 1. Model 3D Robot Head ở góc dưới cùng bên phải
      window.mountRobotHead('aiFabAvatar', {
        shape: 'rectangle',
        size: 72,
        state: 'idle',
        antenna: true,
        interactive: true,
        color: '#1e293b',
        trimColor: '#38bdf8',
        screenColor: '#38bdf8'
      });

      // 2. Header Drawer 3D Robot Head trước tên MEDIBOT
      window.mountRobotHead('aiHeaderAvatar', {
        shape: 'rectangle',
        size: 46,
        state: 'idle',
        antenna: true,
        interactive: true,
        color: '#1e293b',
        trimColor: '#94a3b8',
        screenColor: '#38bdf8'
      });
    }

    // Quản lý trạng thái Model 3D Robot Head
    window.setAiBotState = function(state) {
      if (typeof window.setAllRobotHeadsState === 'function') {
        window.setAllRobotHeadsState(state);
      }
    };

    // Bật/tắt Cửa sổ Trợ lý AI
    function toggleAiChat() {
      const aiDrawer = document.getElementById('aiChatDrawer');
      const accDrawer = document.getElementById('accountDrawer');
      if (!aiDrawer) return;
      if (aiDrawer.style.display === 'none' || aiDrawer.style.display === '') {
        aiDrawer.style.display = 'flex';
        if (accDrawer) accDrawer.style.display = 'none';
        const input = document.getElementById('aiChatInput');
        if (input) input.focus();
        initAiChatGreeting();
        init3DRobotHeads();
        window.setAiBotState('idle');
      } else {
        aiDrawer.style.display = 'none';
      }
    }

    // Lời chào mở đầu cá nhân hóa gọn gàng (không chữ rườm rà)
    function sanitizeCleanAiText(raw) {
      if (raw === null || raw === undefined) return '';
      let str = String(raw);
      // Xoá mọi thẻ HTML
      str = str.replace(/<[^>]*>/g, '');
      // Xoá các tàn dư thẻ dạng /strong, /b, /div, /span, /p, /br
      str = str.replace(/\/(strong|b|i|em|span|div|p|h[1-6]|br)\b/gi, '');
      // Xoá ký tự markdown in đậm, in nghiêng, tiêu đề, backtick
      str = str.replace(/\*\*([^*]+)\*\*/g, '$1');
      str = str.replace(/\*([^*]+)\*/g, '$1');
      str = str.replace(/__([^_]+)__/g, '$1');
      str = str.replace(/_([^_]+)_/g, '$1');
      str = str.replace(/^#{1,6}\s+/gm, '');
      str = str.replace(/`([^`]+)`/g, '$1');
      return str.trim();
    }

    // Khởi tạo lời chào ban đầu cho chatbot (không thẻ HTML, không /strong)
    function initAiChatGreeting() {
      if (aiChatInitialized) return;
      aiChatInitialized = true;
      const box = document.getElementById('aiChatMessages');
      if (!box) return;

      const role = (window.EMR_USER && window.EMR_USER.role) ? window.EMR_USER.role : "guest";
      const name = (window.EMR_USER && window.EMR_USER.name) ? window.EMR_USER.name : "Quý khách";

      let greetText = "";
      if (role === 'member') {
        greetText = `Xin chào ${name}! Tôi là MediBot, trợ lý y tế của MediRecord EMR. Tôi sẵn sàng hỗ trợ bạn tra cứu hồ sơ bệnh án cá nhân hoặc giải đáp các thắc mắc về sức khỏe.`;
      } else if (role === 'doctor') {
        greetText = `Kính chào Bác sĩ ${name}! MediBot sẵn sàng hỗ trợ Bác sĩ tra cứu bệnh án, phác đồ điều trị và quy trình lập hồ sơ mới.`;
      } else if (role === 'admin') {
        greetText = `Xin chào Quản trị viên ${name}! MediBot sẵn sàng hỗ trợ bạn quản trị người dùng, thông báo và giám sát an ninh hệ thống.`;
      } else if (role === 'auditor') {
        greetText = `Xin chào Chuyên viên ${name}! MediBot sẵn sàng hỗ trợ bạn kiểm toán an ninh và chạy bộ 88 ca kiểm thử bảo mật.`;
      } else {
        greetText = `Xin chào quý khách! Tôi là Trợ lý AI MediBot của Hệ thống MediRecord EMR. Bạn cần hỗ trợ gì hôm nay?`;
      }

      appendAiMessage('bot', greetText, 'system');
      window.setAiBotState('idle');
    }

    let botMsgIdx = 0;

    // Tin nhắn trò chuyện phong cách Messenger: Mỗi tin nhắn của bot đều có avatar 3D phía trước
    function appendAiMessage(sender, text, source) {
      const box = document.getElementById('aiChatMessages');
      if (!box) return null;

      botMsgIdx++;
      const rowDiv = document.createElement('div');
      rowDiv.className = 'ai-msg-row ' + (sender === 'user' ? 'user-row' : 'bot-row');

      const cleanText = sanitizeCleanAiText(text);

      let tagHtml = '';
      if (sender === 'bot' && source) {
        if (source === 'openrouter') {
          tagHtml = `<div style="font-size: 10px; color: #6366f1; margin-bottom: 4px; font-weight: 700;">[OPENROUTER AI]</div>`;
        } else if (source === 'local_fallback') {
          tagHtml = `<div style="font-size: 10px; color: #0284c7; margin-bottom: 4px; font-weight: 700;">[TRỢ LÝ MEDIRECORD]</div>`;
        }
      }

      if (sender === 'bot') {
        const avatarId = 'botMsgAvatar_' + botMsgIdx;
        rowDiv.innerHTML = `
          <div class="ai-msg-avatar" id="${avatarId}"></div>
          <div class="ai-msg ai-msg-bot">
            ${tagHtml}
            <div class="ai-msg-content" style="white-space: pre-line; word-break: break-word; line-height: 1.6;"></div>
          </div>
        `;
        rowDiv.querySelector('.ai-msg-content').textContent = cleanText;
        box.appendChild(rowDiv);

        if (typeof window.mountRobotHead === 'function') {
          window.mountRobotHead(avatarId, {
            shape: 'rectangle',
            size: 34,
            state: 'idle',
            antenna: false,
            interactive: false,
            color: '#1e293b',
            trimColor: '#38bdf8',
            screenColor: '#38bdf8'
          });
        }
      } else {
        rowDiv.innerHTML = `
          <div class="ai-msg ai-msg-user">
            <div class="ai-msg-content" style="white-space: pre-line; word-break: break-word; line-height: 1.6;"></div>
          </div>
        `;
        rowDiv.querySelector('.ai-msg-content').textContent = cleanText;
        box.appendChild(rowDiv);
      }

      box.scrollTop = box.scrollHeight;
      return rowDiv.querySelector('.ai-msg');
    }

    // HIỆU ỨNG CHẠY TỪNG CHỮ NHƯ CHATGPT (TYPEWRITER STREAMING)
    function typewriterStream(element, fullText, source, avatarCtrl, onComplete) {
      element.innerHTML = '';
      if (source === 'openrouter') {
        const tag = document.createElement('div');
        tag.style.cssText = 'font-size: 10px; color: #6366f1; margin-bottom: 4px; font-weight: 700;';
        tag.textContent = '[OPENROUTER AI]';
        element.appendChild(tag);
      } else if (source === 'safety_guard') {
        const tag = document.createElement('div');
        tag.style.cssText = 'font-size: 10px; color: #dc2626; margin-bottom: 4px; font-weight: 700;';
        tag.textContent = '[CẢNH BÁO AN TOÀN]';
        element.appendChild(tag);
      } else if (source === 'local_fallback') {
        const tag = document.createElement('div');
        tag.style.cssText = 'font-size: 10px; color: #0284c7; margin-bottom: 4px; font-weight: 700;';
        tag.textContent = '[TRỢ LÝ MEDIRECORD]';
        element.appendChild(tag);
      }

      const contentSpan = document.createElement('div');
      contentSpan.className = 'ai-msg-content';
      contentSpan.style.whiteSpace = 'pre-line';
      contentSpan.style.wordBreak = 'break-word';
      contentSpan.style.lineHeight = '1.6';
      element.appendChild(contentSpan);

      const cursorSpan = document.createElement('span');
      cursorSpan.className = 'ai-typing-cursor';
      cursorSpan.textContent = '▌';
      element.appendChild(cursorSpan);

      const box = document.getElementById('aiChatMessages');
      const cleanFullText = sanitizeCleanAiText(fullText);
      const tokens = cleanFullText.split(/(\s+)/);
      let tokenIdx = 0;
      let currentText = '';

      window.setAiBotState('speaking');
      if (avatarCtrl && avatarCtrl.setState) {
        avatarCtrl.setState('speaking');
      }

      function streamStep() {
        if (tokenIdx < tokens.length) {
          currentText += tokens[tokenIdx];
          tokenIdx++;
          contentSpan.textContent = currentText;
          if (box) box.scrollTop = box.scrollHeight;

          const delay = Math.random() * 10 + 12;
          setTimeout(streamStep, delay);
        } else {
          contentSpan.textContent = currentText;
          if (cursorSpan && cursorSpan.parentNode) {
            cursorSpan.parentNode.removeChild(cursorSpan);
          }
          if (box) box.scrollTop = box.scrollHeight;

          window.setAiBotState('happy');
          if (avatarCtrl && avatarCtrl.setState) {
            avatarCtrl.setState('happy');
          }
          setTimeout(() => {
            window.setAiBotState('idle');
            if (avatarCtrl && avatarCtrl.setState) {
              avatarCtrl.setState('idle');
            }
          }, 1800);

          if (typeof onComplete === 'function') onComplete();
        }
      }

      streamStep();
    }

    function resetAiChat() {
      if (isAiGenerating) return;
      aiChatHistory = [];
      const box = document.getElementById('aiChatMessages');
      if (box) {
        box.innerHTML = '';
      }
      aiChatInitialized = false;
      initAiChatGreeting();
      const input = document.getElementById('aiChatInput');
      if (input) input.value = '';
    }

    function sendQuickPrompt(promptText) {
      const input = document.getElementById('aiChatInput');
      if (!input) return;
      input.value = promptText;
      submitAiChat();
    }

    function submitAiChat() {
      if (isAiGenerating) return;

      const input = document.getElementById('aiChatInput');
      const sendBtn = document.getElementById('aiSendBtn');
      if (!input) return;
      const text = input.value.trim();
      if (!text) return;

      appendAiMessage('user', text);
      input.value = '';

      // Chuẩn bị ngữ cảnh lịch sử trước đó (tối đa 8 lượt gần nhất)
      const historyToSend = aiChatHistory.slice(-8);
      // Nạp tin nhắn hiện tại của user vào lịch sử
      aiChatHistory.push({ role: 'user', content: text });

      isAiGenerating = true;
      if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.innerText = '...';
      }

      window.setAiBotState('thinking');

      // Tạo bot row phong cách Messenger với avatar 3D và bong bóng đang phân tích
      botMsgIdx++;
      const botAvatarId = 'botMsgAvatar_' + botMsgIdx;
      const botRowDiv = document.createElement('div');
      botRowDiv.className = 'ai-msg-row bot-row';
      botRowDiv.innerHTML = `
        <div class="ai-msg-avatar" id="${botAvatarId}"></div>
        <div class="ai-msg ai-msg-bot">
          <span style="color: #94a3b8; font-style: italic;">Đang phân tích dữ liệu...</span> <span class="ai-typing-cursor">▌</span>
        </div>
      `;
      const box = document.getElementById('aiChatMessages');
      if (box) {
        box.appendChild(botRowDiv);
        box.scrollTop = box.scrollHeight;
      }

      let avatarCtrl = null;
      if (typeof window.mountRobotHead === 'function') {
        avatarCtrl = window.mountRobotHead(botAvatarId, {
          shape: 'rectangle',
          size: 34,
          state: 'thinking',
          antenna: false,
          interactive: false,
          color: '#1e293b',
          trimColor: '#38bdf8',
          screenColor: '#38bdf8'
        });
      }

      const botMsgDiv = botRowDiv.querySelector('.ai-msg');

      fetch('/?action=ai_chat_stream', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, history: historyToSend })
      })
      .then(async res => {
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }

        botMsgDiv.innerHTML = '';
        let tagDiv = null;
        const contentDiv = document.createElement('div');
        contentDiv.className = 'ai-msg-content';
        contentDiv.style.whiteSpace = 'pre-wrap';
        contentDiv.style.wordBreak = 'break-word';
        contentDiv.style.lineHeight = '1.6';

        const cursorSpan = document.createElement('span');
        cursorSpan.className = 'ai-typing-cursor';
        cursorSpan.textContent = '▌';

        contentDiv.appendChild(cursorSpan);
        botMsgDiv.appendChild(contentDiv);

        const reader = res.body.getReader();
        const decoder = new TextDecoder('utf-8');
        let accumulatedText = '';
        let streamBuffer = '';
        let currentSource = '';

        const tokenQueue = [];
        let networkDone = false;
        let isFinalized = false;
        let typingInterval = null;

        window.setAiBotState('speaking');
        if (avatarCtrl && avatarCtrl.setState) {
          avatarCtrl.setState('speaking');
        }

        function finalizeBotMessage() {
          if (isFinalized) return;
          isFinalized = true;
          if (typingInterval) {
            clearInterval(typingInterval);
            typingInterval = null;
          }
          if (cursorSpan && cursorSpan.parentNode) {
            cursorSpan.parentNode.removeChild(cursorSpan);
          }
          if (box) box.scrollTop = box.scrollHeight;

          const cleanFinal = sanitizeCleanAiText(accumulatedText);
          if (cleanFinal.trim()) {
            contentDiv.textContent = cleanFinal;
            aiChatHistory.push({ role: 'assistant', content: cleanFinal });
            if (aiChatHistory.length > 10) {
              aiChatHistory = aiChatHistory.slice(-10);
            }
          } else {
            contentDiv.textContent = '[THÔNG BÁO] Không nhận được phản hồi từ mô hình. Vui lòng thử lại!';
            window.setAiBotState('error');
            if (avatarCtrl && avatarCtrl.setState) avatarCtrl.setState('error');
          }

          window.setAiBotState('happy');
          if (avatarCtrl && avatarCtrl.setState) {
            avatarCtrl.setState('happy');
          }
          setTimeout(() => {
            window.setAiBotState('idle');
            if (avatarCtrl && avatarCtrl.setState) avatarCtrl.setState('idle');
          }, 1800);

          isAiGenerating = false;
          if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.innerText = 'Gửi';
          }
        }

        function drainQueue() {
          if (tokenQueue.length === 0) {
            if (networkDone) {
              finalizeBotMessage();
            }
            return;
          }

          // Điều tiết tốc độ gõ chữ thích ứng: gõ đều từng từ/ký tự mượt mà kèm hiệu ứng fade
          const count = tokenQueue.length > 30 ? 4 : (tokenQueue.length > 10 ? 2 : 1);
          for (let i = 0; i < count && tokenQueue.length > 0; i++) {
            const token = tokenQueue.shift();
            accumulatedText += token;

            const span = document.createElement('span');
            span.className = 'ai-fade-token';
            span.textContent = token;
            contentDiv.insertBefore(span, cursorSpan);
          }

          if (box) {
            box.scrollTop = box.scrollHeight;
          }
        }

        typingInterval = setInterval(drainQueue, 22);

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          streamBuffer += decoder.decode(value, { stream: true });
          const lines = streamBuffer.split('\n');
          streamBuffer = lines.pop(); // Giữ lại phần chưa có ký tự xuống dòng

          for (let line of lines) {
            line = line.trim();
            if (!line.startsWith('data: ')) continue;
            const dataStr = line.slice(6);
            if (dataStr === '[DONE]') break;

            try {
              const dataObj = JSON.parse(dataStr);
              if (dataObj.source && dataObj.source !== currentSource) {
                currentSource = dataObj.source;
                if (!tagDiv) {
                  tagDiv = document.createElement('div');
                  tagDiv.style.cssText = 'font-size: 10px; margin-bottom: 4px; font-weight: 700;';
                  if (currentSource === 'openrouter') {
                    tagDiv.style.color = '#6366f1';
                    tagDiv.textContent = '[OPENROUTER AI]';
                  } else if (currentSource === 'safety_guard') {
                    tagDiv.style.color = '#dc2626';
                    tagDiv.textContent = '[CẢNH BÁO AN TOÀN]';
                  } else {
                    tagDiv.style.color = '#0284c7';
                    tagDiv.textContent = '[TRỢ LÝ MEDIRECORD]';
                  }
                  botMsgDiv.insertBefore(tagDiv, contentDiv);
                }
              }
              if (dataObj.delta) {
                // Phân tách các từ và khoảng trắng để hiển thị dần dần và mượt mà
                const pieces = dataObj.delta.match(/(\r?\n|[^\S\r\n]+|\S+)/g) || [dataObj.delta];
                for (let p of pieces) {
                  tokenQueue.push(p);
                }
              }
            } catch(e) {}
          }
        }

        networkDone = true;
        if (tokenQueue.length === 0) {
          finalizeBotMessage();
        }
      })
      .catch(err => {
        if (typeof typingInterval !== 'undefined' && typingInterval) {
          clearInterval(typingInterval);
        }
        aiChatHistory.pop();
        botMsgDiv.textContent = '[LỖI MẠNG] Không thể kết nối máy chủ: ' + err;
        window.setAiBotState('error');
        if (avatarCtrl && avatarCtrl.setState) avatarCtrl.setState('error');
        isAiGenerating = false;
        if (sendBtn) {
          sendBtn.disabled = false;
          sendBtn.innerText = 'Gửi';
        }
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      init3DRobotHeads();
      const aiIn = document.getElementById('aiChatInput');
      if (aiIn) {
        aiIn.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            submitAiChat();
          }
        });
      }
    });

    // Điền nhanh thông tin đăng nhập
    function fillLogin(username, password) {
      const uInput = document.getElementById('loginUsername');
      const pInput = document.getElementById('loginPassword');
      if (uInput) uInput.value = username;
      if (pInput) pInput.value = password;
      if (uInput) uInput.focus();
    }

    // Modal quản lý người dùng
    function openAddUserModal() {
      const m = document.getElementById('addUserModal');
      if (m) m.style.display = 'flex';
    }
    function closeAddUserModal() {
      const m = document.getElementById('addUserModal');
      if (m) m.style.display = 'none';
    }

    function openEditUserModal(username, fullName, role, dept) {
      const u = document.getElementById('editUsername');
      if (u) {
        u.value = username;
        document.getElementById('displayEditUsername').value = username;
        document.getElementById('editFullName').value = fullName;
        document.getElementById('editRole').value = role;
        document.getElementById('editDept').value = dept;
        document.getElementById('editUserModal').style.display = 'flex';
      }
    }
    function closeEditUserModal() {
      const m = document.getElementById('editUserModal');
      if (m) m.style.display = 'none';
    }

    // Modal quản lý thông báo bệnh viện (Admin)
    function openAddAnnouncementModal(tab) {
      const audEl = document.getElementById('addAnnouncementAudience');
      if (audEl && tab) {
        audEl.value = (tab === 'internal') ? 'internal' : 'patient';
      }
      const m = document.getElementById('addAnnouncementModal');
      if (m) m.style.display = 'flex';
    }
    function closeAddAnnouncementModal() {
      const m = document.getElementById('addAnnouncementModal');
      if (m) m.style.display = 'none';
    }
    function openEditAnnouncementModal(id, title, body, audience) {
      const idEl = document.getElementById('editAnnouncementId');
      const titleEl = document.getElementById('editAnnouncementTitle');
      const bodyEl = document.getElementById('editAnnouncementBody');
      const audEl = document.getElementById('editAnnouncementAudience');
      if (idEl && titleEl && bodyEl) {
        idEl.value = id;
        titleEl.value = title;
        bodyEl.value = body;
        if (audEl && audience) {
          audEl.value = audience;
        }
        const m = document.getElementById('editAnnouncementModal');
        if (m) m.style.display = 'flex';
      }
    }
    function closeEditAnnouncementModal() {
      const m = document.getElementById('editAnnouncementModal');
      if (m) m.style.display = 'none';
    }

    // Tìm kiếm người dùng tức thì kết hợp phân trang
    function filterUsers() {
      const input = document.getElementById('userSearchInput');
      if (!input) return;
      const filter = input.value.toLowerCase();
      const table = document.getElementById('userTable');
      if (!table) return;
      const trs = table.querySelectorAll('tbody tr');

      trs.forEach(tr => {
        if (tr.querySelector('td[colspan]')) return;
        const text = tr.textContent.toLowerCase();
        if (text.indexOf(filter) > -1) {
          tr.dataset.filteredOut = 'false';
        } else {
          tr.dataset.filteredOut = 'true';
        }
      });
      if (window.__pager_usersPagination) {
        window.__pager_usersPagination.reset();
      }
    }

    // Bộ máy phân trang tối ưu (Taste-Skill compliant pagination engine)
    function setupPagination(opts) {
      const container = document.getElementById(opts.containerId);
      const wrap = document.getElementById(opts.paginationWrapId);
      if (!container || !wrap) return null;

      const pageSize = opts.pageSize || 5;
      const isTable = opts.isTable !== false;
      let currentPage = 1;

      function getItems() {
        if (isTable) {
          const tbody = container.querySelector('tbody');
          if (!tbody) return [];
          return Array.from(tbody.querySelectorAll('tr')).filter(tr => !tr.querySelector('td[colspan]'));
        } else {
          return Array.from(container.children).filter(el => el.classList.contains('announcement-card') || el.dataset.pageable === 'true');
        }
      }

      function render() {
        const allItems = getItems();
        const visibleItems = allItems.filter(el => el.dataset.filteredOut !== 'true');
        const totalItems = visibleItems.length;

        if (totalItems === 0) {
          wrap.innerHTML = '';
          wrap.style.display = 'none';
          return;
        }
        wrap.style.display = 'flex';

        const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        let visibleIndex = 0;
        allItems.forEach(item => {
          if (item.dataset.filteredOut === 'true') {
            item.style.display = 'none';
            return;
          }
          const pageIndex = Math.floor(visibleIndex / pageSize) + 1;
          if (pageIndex === currentPage) {
            item.style.display = isTable ? '' : 'block';
          } else {
            item.style.display = 'none';
          }
          visibleIndex++;
        });

        const startNum = (currentPage - 1) * pageSize + 1;
        const endNum = Math.min(currentPage * pageSize, totalItems);

        let html = `
          <div class="pagination-info">
            Hiển thị <strong>${startNum} - ${endNum}</strong> trong tổng số <strong>${totalItems}</strong> mục
          </div>
          <div class="pagination-controls">
            <button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.__pager_${opts.paginationWrapId}.goTo(1)" title="Trang đầu">&laquo;</button>
            <button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.__pager_${opts.paginationWrapId}.goTo(${currentPage - 1})" title="Trang trước">&lsaquo;</button>
        `;

        for (let p = 1; p <= totalPages; p++) {
          if (totalPages <= 7 || p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)) {
            html += `<button class="page-btn ${p === currentPage ? 'active' : ''}" onclick="window.__pager_${opts.paginationWrapId}.goTo(${p})">${p}</button>`;
          } else if (p === currentPage - 2 || p === currentPage + 2) {
            html += `<span style="padding: 2px 4px; color: #94a3b8;">...</span>`;
          }
        }

        html += `
            <button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.__pager_${opts.paginationWrapId}.goTo(${currentPage + 1})" title="Trang sau">&rsaquo;</button>
            <button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.__pager_${opts.paginationWrapId}.goTo(${totalPages})" title="Trang cuối">&raquo;</button>
          </div>
        `;

        wrap.innerHTML = html;
      }

      const pagerObj = {
        goTo: function(p) { currentPage = p; render(); },
        reset: function() { currentPage = 1; render(); },
        refresh: function() { render(); }
      };

      window['__pager_' + opts.paginationWrapId] = pagerObj;
      render();
      return pagerObj;
    }

    document.addEventListener('DOMContentLoaded', function() {
      setupPagination({ containerId: 'recordsTable', paginationWrapId: 'recordsPagination', pageSize: 5, isTable: true });
      setupPagination({ containerId: 'userTable', paginationWrapId: 'usersPagination', pageSize: 5, isTable: true });
      setupPagination({ containerId: 'incidentsTable', paginationWrapId: 'incidentsPagination', pageSize: 5, isTable: true });
      setupPagination({ containerId: 'testCasesTable', paginationWrapId: 'testCasesPagination', pageSize: 6, isTable: true });
      setupPagination({ containerId: 'announcementsList', paginationWrapId: 'announcementsPagination', pageSize: 3, isTable: false });
    });

    // =========================================================================
    // MANUAL ACCESS TEST CASE RUNNER & RENAME RUN CONTROLLER
    // =========================================================================
    window.toggleRenameRunForm = function(runId) {
      const form = document.getElementById('rename-form-' + runId);
      if (!form) return;
      form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none';
    };

    window.runSingleManualCase = function(code, btnEl) {
      if (!code) return;
      if (btnEl) {
        btnEl.disabled = true;
        btnEl.textContent = 'Đang kiểm tra...';
      }

      fetch('/?action=run_single_test_case&ajax=1&code=' + encodeURIComponent(code), {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      })
      .then(res => res.json())
      .then(data => {
        if (data && data.ok && data.case) {
          const tc = data.case;
          const rowEl = document.getElementById('row-tc-' + code);
          const actualEl = document.getElementById('actual-tc-' + code);
          const statusEl = document.getElementById('status-tc-' + code);

          if (actualEl) {
            actualEl.style.fontStyle = 'normal';
            actualEl.style.color = tc.status === 'FAIL' ? '#b91c1c' : '#0f172a';
            actualEl.innerHTML = '';
            const txtNode = document.createTextNode(tc.actual || '');
            actualEl.appendChild(txtNode);
            if (tc.latency_ms) {
              const latSpan = document.createElement('span');
              latSpan.style.cssText = 'font-size: 10px; color: #64748b; margin-left: 4px;';
              latSpan.textContent = '(' + tc.latency_ms + 'ms)';
              actualEl.appendChild(latSpan);
            }
          }

          if (statusEl) {
            if (tc.status === 'PASS') {
              statusEl.innerHTML = '<span style="font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px; background: #dcfce7; color: #15803d;">PASS</span>';
            } else {
              statusEl.innerHTML = '<span style="font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px; background: #fee2e2; color: #b91c1c;">FAIL</span>';
            }
          }

          if (rowEl) {
            if (tc.status === 'FAIL') {
              rowEl.style.background = '#fff1f2';
            } else if (tc.code === 'R02' && tc.status === 'PASS') {
              rowEl.style.background = '#f0fdf4';
            } else {
              rowEl.style.background = '';
            }
          }

          if (data.summary) {
            const s = data.summary;
            const execEl = document.getElementById('matrixExecCount');
            const passEl = document.getElementById('matrixPassCount');
            const failEl = document.getElementById('matrixFailCount');
            const pendEl = document.getElementById('matrixPendCount');
            const kpiMain = document.getElementById('kpiManualMainVal');
            const kpiSub = document.getElementById('kpiManualSubVal');
            const topBadge = document.getElementById('matrixTopBadgeWrap');

            if (execEl) execEl.textContent = s.executed + '/24';
            if (passEl) passEl.textContent = s.passed;
            if (failEl) failEl.textContent = s.failed;
            if (pendEl) pendEl.textContent = s.pending;

            if (kpiMain) {
              kpiMain.style.color = s.failed > 0 ? '#b45309' : '#15803d';
              kpiMain.textContent = s.passed + ' PASS / ' + s.failed + ' FAIL';
            }
            if (kpiSub) {
              kpiSub.textContent = s.pending > 0
                ? ('Đã chạy ' + s.executed + '/24 ca (Còn ' + s.pending + ' ca chờ)')
                : 'Hoàn tất 24/24 ca kiểm thử thủ công';
            }
            if (topBadge) {
              if (s.failed > 0) {
                topBadge.innerHTML = '<span style="background: #fee2e2; color: #b91c1c; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px;">' + s.passed + ' PASS / ' + s.failed + ' FAIL ' + (s.pending > 0 ? '(' + s.pending + ' CHỜ)' : '') + '</span>';
              } else {
                topBadge.innerHTML = '<span style="background: #dcfce7; color: #15803d; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px;">' + s.passed + ' PASS / 0 FAIL ' + (s.pending > 0 ? '(' + s.pending + ' CHỜ)' : '') + '</span>';
              }
            }
          }
        }
        if (btnEl) {
          btnEl.disabled = false;
          btnEl.textContent = 'Chạy lại';
        }
      })
      .catch(() => {
        window.location.href = '/?action=run_single_test_case&code=' + encodeURIComponent(code);
      });
    };

    // =========================================================================
    // HOLD TO UNLOCK / CONFIRM DIAL CONTROLLER (GIỮ ĐỂ MỞ KHOÁ XÁC NHẬN)
    // =========================================================================
    (function initHoldLockController() {
      const modal = document.getElementById('holdConfirmModal');
      const titleEl = document.getElementById('holdModalTitle');
      const descEl = document.getElementById('holdModalDesc');
      const badgeEl = document.getElementById('holdModalBadge');
      const btn = document.getElementById('holdLockButton');
      const ring = document.querySelector('#globalHoldLock .lp-ring');
      const progressCircle = document.getElementById('holdLockProgress');
      const shackle = document.getElementById('holdLockShackle');
      const label = document.getElementById('holdLockLabel');

      if (!btn || !progressCircle || !label) return;

      const RADIUS = 72;
      const CIRCUMFERENCE = 2 * Math.PI * RADIUS; // ~452.389
      progressCircle.style.strokeDasharray = CIRCUMFERENCE + ' ' + CIRCUMFERENCE;
      progressCircle.style.strokeDashoffset = CIRCUMFERENCE;

      let holdProgress = 0;
      let isHolding = false;
      let isUnlocked = false;
      let startHoldTime = 0;
      let animFrameId = null;
      let currentConfirmCallback = null;
      const HOLD_TIME_MS = 1000; // 1 giây giữ

      function updateVisuals(p) {
        p = Math.max(0, Math.min(1, p));
        const offset = CIRCUMFERENCE * (1 - p);
        progressCircle.style.strokeDashoffset = offset;
        progressCircle.style.opacity = p > 0.02 ? 1 : 0.3;
        if (shackle) {
          shackle.style.transform = `translateY(${-p * 4}px)`;
        }
      }

      function setHoldLabel(txt, isSuccess) {
        if (!label) return;
        label.textContent = txt;
        if (isSuccess) {
          label.classList.add('is-success');
        } else {
          label.classList.remove('is-success');
        }
      }

      function unlockDial() {
        isUnlocked = true;
        isHolding = false;
        btn.classList.add('lp-unlocked');
        btn.classList.remove('is-holding');
        updateVisuals(1);
        if (shackle) shackle.style.transform = 'translateY(-6px)';
        if (ring) {
          ring.style.transform = 'scale(1.1)';
          ring.style.opacity = '0';
        }
        setHoldLabel('Đã Mở Khoá & Xác Nhận Thành Công!', true);
        if (navigator.vibrate) {
          try { navigator.vibrate([15, 30, 20]); } catch (e) {}
        }

        setTimeout(() => {
          const cb = currentConfirmCallback;
          closeHoldConfirmModal();
          if (typeof cb === 'function') {
            cb();
          }
        }, 380);
      }

      function resetDial() {
        isHolding = false;
        isUnlocked = false;
        btn.classList.remove('lp-unlocked');
        btn.classList.remove('is-holding');
        if (ring) {
          ring.style.transform = 'scale(1)';
          ring.style.opacity = '1';
        }
        setHoldLabel('Nhấn và giữ ổ khoá để xác nhận', false);
        decayToZero();
      }

      function decayToZero() {
        if (isHolding || isUnlocked) return;
        if (holdProgress <= 0.01) {
          holdProgress = 0;
          updateVisuals(0);
          return;
        }
        holdProgress += (0 - holdProgress) * 0.22;
        updateVisuals(holdProgress);
        requestAnimationFrame(decayToZero);
      }

      function holdStep(now) {
        if (!isHolding || isUnlocked) return;
        const elapsed = now - startHoldTime;
        holdProgress = Math.min(1, elapsed / HOLD_TIME_MS);
        updateVisuals(holdProgress);

        if (holdProgress >= 1) {
          unlockDial();
        } else {
          setHoldLabel('Đang giữ xác nhận (' + Math.round(holdProgress * 100) + '%)...', false);
          animFrameId = requestAnimationFrame(holdStep);
        }
      }

      function startHold(e) {
        if (isUnlocked) return;
        if (e.button !== undefined && e.button !== 0) return; // Chỉ chuột trái
        isHolding = true;
        btn.classList.add('is-holding');
        startHoldTime = performance.now() - (holdProgress * HOLD_TIME_MS);
        setHoldLabel('Giữ yên ổ khoá...', false);
        if (navigator.vibrate) {
          try { navigator.vibrate(10); } catch (e) {}
        }
        if (animFrameId) cancelAnimationFrame(animFrameId);
        animFrameId = requestAnimationFrame(holdStep);
      }

      function cancelHold() {
        if (isUnlocked) return;
        if (isHolding) {
          isHolding = false;
          btn.classList.remove('is-holding');
          if (animFrameId) cancelAnimationFrame(animFrameId);
          setHoldLabel('Nhấn và giữ ổ khoá để xác nhận', false);
          decayToZero();
        }
      }

      btn.addEventListener('pointerdown', startHold);
      btn.addEventListener('pointerup', cancelHold);
      btn.addEventListener('pointerleave', cancelHold);
      btn.addEventListener('pointercancel', cancelHold);

      btn.addEventListener('keydown', function(e) {
        if ((e.key === ' ' || e.key === 'Enter') && !isHolding && !isUnlocked) {
          e.preventDefault();
          startHold(e);
        }
      });
      btn.addEventListener('keyup', function(e) {
        if (e.key === ' ' || e.key === 'Enter') {
          cancelHold();
        }
      });

      // Public APIs
      window.showHoldConfirmModal = function(opts) {
        opts = opts || {};
        if (titleEl) titleEl.textContent = opts.title || 'Giữ Để Mở Khoá Xác Nhận';
        if (descEl) descEl.textContent = opts.message || 'Vui lòng nhấn và giữ biểu tượng ổ khóa bên dưới trong 1 giây để hoàn tất thao tác quan trọng này.';
        if (badgeEl) badgeEl.textContent = opts.badge || 'XÁC NHẬN BẢO MẬT';
        currentConfirmCallback = opts.onConfirm || null;
        resetDial();
        if (modal) modal.style.display = 'flex';
      };

      window.closeHoldConfirmModal = function() {
        if (modal) modal.style.display = 'none';
        resetDial();
        currentConfirmCallback = null;
      };

      window.confirmWithHold = function(e, opts) {
        if (e && e.preventDefault) e.preventDefault();
        const targetHref = (e && e.currentTarget && e.currentTarget.href) ? e.currentTarget.href : null;
        opts = opts || {};
        window.showHoldConfirmModal({
          title: opts.title || 'Xác Nhận Thao Tác',
          message: opts.message || 'Bạn có chắc chắn muốn thực hiện hành động này?',
          badge: opts.badge || 'XÁC THỰC AN TOÀN',
          onConfirm: function() {
            if (typeof opts.onConfirm === 'function') {
              opts.onConfirm();
            } else if (targetHref) {
              window.location.href = targetHref;
            }
          }
        });
        return false;
      };

      window.confirmFormWithHold = function(e, form, opts) {
        if (e && e.preventDefault) e.preventDefault();
        opts = opts || {};
        window.showHoldConfirmModal({
          title: opts.title || 'Xác Nhận Gửi Dữ Liệu',
          message: opts.message || 'Bạn có chắc chắn muốn lưu các thay đổi này?',
          badge: opts.badge || 'XÁC NHẬN LƯU',
          onConfirm: function() {
            if (form && typeof form.submit === 'function') {
              form.submit();
            }
          }
        });
        return false;
      };
    })();
