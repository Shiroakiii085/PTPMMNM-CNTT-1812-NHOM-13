/**
 * MediSec Audit - Standard UI Helper
 * High-taste anti-slop: Client-side instant search, scientific pagination,
 * collapsible inspection rows, and tab switching. Zero external bloat.
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Universal Table Pagination & Real-time Search Controller
    const paginatedTables = document.querySelectorAll('[data-paginate]');
    paginatedTables.forEach(tableContainer => {
        const table = tableContainer.querySelector('table');
        if (!table) return;

        const pageSize = parseInt(tableContainer.getAttribute('data-page-size') || '8', 10);
        const searchInput = tableContainer.querySelector('[data-table-search]') || document.querySelector(tableContainer.getAttribute('data-search-input') || '');
        if (searchInput) {
            searchInput.setAttribute('data-managed', 'true');
        }
        const paginationBox = tableContainer.querySelector('.pagination-controls');
        const countDisplay = tableContainer.querySelector('.pagination-info');

        let currentPage = 1;
        let filteredRows = [];

        // Lấy danh sách hàng hợp lệ trong tbody (loại trừ hàng chi tiết hoặc no-search)
        function getAllRows() {
            return Array.from(table.querySelectorAll('tbody tr:not(.no-search):not(.detail-row)'));
        }

        function applyFilter() {
            const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const allRows = getAllRows();

            filteredRows = allRows.filter(row => {
                const text = row.textContent.toLowerCase();
                const match = !query || text.includes(query);
                if (!match) {
                    row.style.display = 'none';
                    // Ẩn luôn hàng detail liền kề nếu có
                    const nextRow = row.nextElementSibling;
                    if (nextRow && nextRow.classList.contains('detail-row')) {
                        nextRow.style.display = 'none';
                    }
                }
                return match;
            });

            currentPage = 1;
            renderPage();
        }

        function renderPage() {
            const total = filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            // Ẩn tất cả hàng
            getAllRows().forEach(row => {
                row.style.display = 'none';
                const nextRow = row.nextElementSibling;
                if (nextRow && nextRow.classList.contains('detail-row')) {
                    nextRow.style.display = 'none';
                }
            });

            // Hiện các hàng thuộc trang hiện tại
            filteredRows.slice(startIndex, endIndex).forEach(row => {
                row.style.display = '';
            });

            // Cập nhật thông tin số lượng
            if (countDisplay) {
                if (total === 0) {
                    countDisplay.textContent = 'Không có kết quả phù hợp';
                } else {
                    const startCount = startIndex + 1;
                    const endCount = Math.min(endIndex, total);
                    countDisplay.textContent = `Hiển thị ${startCount}–${endCount} trong tổng số ${total} bản ghi`;
                }
            }

            // Vẽ nút phân trang
            if (paginationBox) {
                paginationBox.innerHTML = '';
                if (totalPages <= 1 && total <= pageSize) {
                    paginationBox.style.display = 'none';
                    return;
                }
                paginationBox.style.display = 'flex';

                // Nút Trước
                const prevBtn = document.createElement('button');
                prevBtn.type = 'button';
                prevBtn.className = 'btn btn-outline btn-sm';
                prevBtn.textContent = 'Trang trước';
                prevBtn.disabled = (currentPage === 1);
                prevBtn.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage--;
                        renderPage();
                    }
                });
                paginationBox.appendChild(prevBtn);

                // Các nút số trang
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.type = 'button';
                    pageBtn.className = (i === currentPage) ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
                    pageBtn.textContent = i.toString();
                    pageBtn.style.minWidth = '32px';
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        renderPage();
                    });
                    paginationBox.appendChild(pageBtn);
                }

                // Nút Sau
                const nextBtn = document.createElement('button');
                nextBtn.type = 'button';
                nextBtn.className = 'btn btn-outline btn-sm';
                nextBtn.textContent = 'Trang sau';
                nextBtn.disabled = (currentPage === totalPages);
                nextBtn.addEventListener('click', () => {
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderPage();
                    }
                });
                paginationBox.appendChild(nextBtn);
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilter);
        }

        // Khởi tạo ban đầu
        applyFilter();
    });

    // 1b. Run History Card List Pagination & Real-time Search
    const runHistoryContainer = document.getElementById('runsHistoryContainer');
    if (runHistoryContainer) {
        const searchInput = document.getElementById('runSearchInput');
        const items = Array.from(runHistoryContainer.querySelectorAll('.run-card-item'));
        const paginationControls = document.getElementById('historyPaginationControls');
        const paginationInfo = document.getElementById('historyPaginationInfo');
        const paginationBtns = document.getElementById('historyPaginationBtns');
        const pageSize = parseInt(runHistoryContainer.getAttribute('data-page-size') || '6', 10);

        let currentPage = 1;
        let filteredItems = items;

        function applyRunFilter() {
            const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
            filteredItems = items.filter(item => {
                const text = (item.getAttribute('data-search-text') || item.textContent).toLowerCase();
                return !query || text.includes(query);
            });
            currentPage = 1;
            renderRunPage();
        }

        function renderRunPage() {
            const total = filteredItems.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            // Hide all items
            items.forEach(item => {
                item.style.display = 'none';
            });

            // Show items in current page
            filteredItems.slice(startIndex, endIndex).forEach(item => {
                item.style.display = 'block';
            });

            // Update info
            if (paginationInfo) {
                if (total === 0) {
                    paginationInfo.textContent = '0 kết quả';
                } else {
                    paginationInfo.textContent = `${startIndex + 1}–${Math.min(endIndex, total)} / ${total}`;
                }
            }

            // Render buttons
            if (paginationBtns && paginationControls) {
                paginationBtns.innerHTML = '';
                if (totalPages <= 1) {
                    paginationControls.style.display = (total > 0 && total <= pageSize && (!searchInput || !searchInput.value)) ? 'none' : 'flex';
                    if (totalPages <= 1) return;
                }
                paginationControls.style.display = 'flex';

                // Prev button
                const prevBtn = document.createElement('button');
                prevBtn.type = 'button';
                prevBtn.className = 'btn btn-outline btn-sm';
                prevBtn.textContent = '‹';
                prevBtn.style.padding = '2px 8px';
                prevBtn.style.fontSize = '12px';
                prevBtn.disabled = (currentPage === 1);
                prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; renderRunPage(); } };
                paginationBtns.appendChild(prevBtn);

                // Page numbers
                for (let i = 1; i <= totalPages; i++) {
                    if (totalPages > 5 && (i !== 1 && i !== totalPages && Math.abs(i - currentPage) > 1)) {
                        if (i === 2 && currentPage > 3) {
                            const dots = document.createElement('span');
                            dots.textContent = '…';
                            dots.style.padding = '0 2px';
                            dots.style.fontSize = '11px';
                            dots.style.color = '#a1a1aa';
                            paginationBtns.appendChild(dots);
                        } else if (i === totalPages - 1 && currentPage < totalPages - 2) {
                            const dots = document.createElement('span');
                            dots.textContent = '…';
                            dots.style.padding = '0 2px';
                            dots.style.fontSize = '11px';
                            dots.style.color = '#a1a1aa';
                            paginationBtns.appendChild(dots);
                        }
                        continue;
                    }
                    const pageBtn = document.createElement('button');
                    pageBtn.type = 'button';
                    pageBtn.className = (i === currentPage) ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
                    pageBtn.textContent = i.toString();
                    pageBtn.style.padding = '2px 7px';
                    pageBtn.style.fontSize = '11px';
                    pageBtn.style.minWidth = '24px';
                    pageBtn.onclick = () => { currentPage = i; renderRunPage(); };
                    paginationBtns.appendChild(pageBtn);
                }

                // Next button
                const nextBtn = document.createElement('button');
                nextBtn.type = 'button';
                nextBtn.className = 'btn btn-outline btn-sm';
                nextBtn.textContent = '›';
                nextBtn.style.padding = '2px 8px';
                nextBtn.style.fontSize = '12px';
                nextBtn.disabled = (currentPage === totalPages);
                nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; renderRunPage(); } };
                paginationBtns.appendChild(nextBtn);
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyRunFilter);
        }

        // If there's an active selected run, make sure its page is shown initially
        const activeItem = runHistoryContainer.querySelector('.run-card-item[style*="#10b981"], .run-card-item[style*="#ecfdf5"]');
        if (activeItem) {
            const activeIndex = items.indexOf(activeItem);
            if (activeIndex >= 0) {
                currentPage = Math.floor(activeIndex / pageSize) + 1;
            }
        }

        applyRunFilter();
    }

    // 2. Standalone Real-time Search Filter cho các bảng không dùng container phân trang
    const independentSearchInputs = document.querySelectorAll('[data-table-search]:not([data-managed])');
    independentSearchInputs.forEach(input => {
        const tableSelector = input.getAttribute('data-table-search');
        const table = document.querySelector(tableSelector);
        if (!table) return;

        input.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr:not(.no-search)');
            let matchCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    matchCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const countDisplay = document.querySelector('[data-search-count]');
            if (countDisplay) {
                countDisplay.textContent = query ? `${matchCount} kết quả` : '';
            }
        });
    });

    // 3. Expandable Row Details Toggle
    document.querySelectorAll('[data-toggle-detail]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = btn.getAttribute('data-toggle-detail');
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                const isHidden = targetEl.style.display === 'none' || !targetEl.style.display;
                targetEl.style.display = isHidden ? 'table-row' : 'none';
                btn.textContent = isHidden ? 'Thu gọn' : 'Chi tiết';
            }
        });
    });

    // 4. Tab Switcher
    document.querySelectorAll('.nav-tab-btn[data-tab-target]').forEach(tabBtn => {
        tabBtn.addEventListener('click', () => {
            const container = tabBtn.closest('.tab-wrapper') || document;
            const target = tabBtn.getAttribute('data-tab-target');

            tabBtn.parentElement.querySelectorAll('.nav-tab-btn').forEach(b => b.classList.remove('active'));
            tabBtn.classList.add('active');

            container.querySelectorAll('.tab-pane').forEach(pane => {
                if (pane.id === target) {
                    pane.style.display = 'block';
                } else {
                    pane.style.display = 'none';
                }
            });
        });
    });

    // 5. Quick Copy Button
    document.querySelectorAll('[data-copy-text]').forEach(btn => {
        btn.addEventListener('click', () => {
            const text = btn.getAttribute('data-copy-text');
            navigator.clipboard.writeText(text).then(() => {
                const originalText = btn.textContent;
                btn.textContent = 'Đã sao chép';
                setTimeout(() => {
                    btn.textContent = originalText;
                }, 1500);
            });
        });
    });
});
