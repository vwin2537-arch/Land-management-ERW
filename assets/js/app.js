/**
 * App JavaScript — ระบบจัดการที่ดินทำกิน
 */

// ===== Sidebar Toggle (Mobile) =====
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}

// Close sidebar on outside click (mobile)
document.addEventListener('click', function (e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    if (sidebar && sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) &&
        (!toggle || !toggle.contains(e.target))) {
        sidebar.classList.remove('open');
    }
});

// ===== Live Search & Global Search =====
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// 1. Global Search (Top Bar)
const globalSearch = document.getElementById('globalSearch');
if (globalSearch) {
    globalSearch.addEventListener('input', debounce(function () {
        const query = this.value.trim();
        if (query.length >= 2) {
            // Navigate to villagers search by default for global search
            window.location.href = `index.php?page=villagers&search=${encodeURIComponent(query)}`;
        }
    }, 800)); // Wait 800ms after last keystroke

    // Allow Enter key to search immediately
    globalSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query.length >= 2) {
                window.location.href = `index.php?page=villagers&search=${encodeURIComponent(query)}`;
            }
        }
    });
}

// 2. Form Live Search (Auto-submit on typing)
document.addEventListener('input', debounce(function (e) {
    if (e.target && e.target.matches('[data-live-search="true"]')) {
        const form = e.target.closest('form');
        if (form) {
            form.submit();
        }
    }
}, 600)); // Slightly faster for inner search (600ms)

// ===== Alert Auto-dismiss =====
document.querySelectorAll('.alert[data-dismiss]').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }, 4000);
});

// ===== Confirm Delete =====
function confirmDelete(message = 'คุณต้องการลบข้อมูลนี้ใช่หรือไม่?') {
    return confirm(message);
}

// ===== Format Number with Commas =====
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// ===== Format Thai Date =====
function formatThaiDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
        'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear() + 543;
    return `${day} ${month} ${year}`;
}

// ===== Toast Notification =====
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 9999;
        min-width: 300px; animation: slideIn 0.3s ease;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    `;

    const icons = {
        success: 'bi-check-circle-fill',
        danger: 'bi-x-circle-fill',
        warning: 'bi-exclamation-circle-fill',
        info: 'bi-info-circle-fill',
    };

    toast.innerHTML = `<i class="bi ${icons[type] || icons.info}"></i> ${message}`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== Print PDF Helper =====
function printPDF(url) {
    window.open(url, '_blank');
}

console.log('🌳 ระบบจัดการที่ดินทำกิน — Ready');
