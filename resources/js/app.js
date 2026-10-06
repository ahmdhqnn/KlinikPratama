import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// CSRF token for fetch requests
window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// Global helpers
window.formatRupiah = (amount) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

window.formatNumber = (num) => {
    return new Intl.NumberFormat('id-ID').format(num);
};

// Auto-dismiss flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        });
    }, 5000);
});

// Confirm delete dialogs
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
        const message = el.getAttribute('data-confirm') || 'Apakah Anda yakin?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});
