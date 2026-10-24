/**
 * AccessTester Enterprise UI Helper - Smooth Interactions & Analytics
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Client-side Real-time Search Filter for Tables
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
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

    // 2. Expandable Row Details Toggle
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

    // 3. Simple Tab Switcher (Without Hash Flash)
    document.querySelectorAll('.nav-tab-btn[data-tab-target]').forEach(tabBtn => {
        tabBtn.addEventListener('click', () => {
            const container = tabBtn.closest('.tab-wrapper') || document;
            const target = tabBtn.getAttribute('data-tab-target');

            // Deactivate sibling tabs
            tabBtn.parentElement.querySelectorAll('.nav-tab-btn').forEach(b => b.classList.remove('active'));
            tabBtn.classList.add('active');

            // Toggle panes
            container.querySelectorAll('.tab-pane').forEach(pane => {
                if (pane.id === target) {
                    pane.style.display = 'block';
                    pane.style.animation = 'fadeIn 0.25s ease';
                } else {
                    pane.style.display = 'none';
                }
            });
        });
    });

    // 4. Quick Copy Button with Feedback Badge
    document.querySelectorAll('[data-copy-text]').forEach(btn => {
        btn.addEventListener('click', () => {
            const text = btn.getAttribute('data-copy-text');
            navigator.clipboard.writeText(text).then(() => {
                const originalText = btn.textContent;
                btn.textContent = 'ĐÃ SAO CHÉP';
                btn.classList.add('btn-primary');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('btn-primary');
                }, 1500);
            });
        });
    });
});
