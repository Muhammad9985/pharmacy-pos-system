// Admin Panel JavaScript Utilities

// Enhanced form validation
document.addEventListener('DOMContentLoaded', function() {
    // Add loading states to buttons
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
                submitBtn.disabled = true;
            }
        });
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
            if (alert.classList.contains('show')) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            }
        });
    }, 5000);

    // Add tooltips to buttons
    document.querySelectorAll('[title]').forEach(element => {
        element.setAttribute('data-bs-toggle', 'tooltip');
    });

    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

// Utility functions
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toLocaleString('en-PK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PK', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Enhanced table functionality
function initializeDataTables() {
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        $('.table-modern').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }
}

// Print functionality
function printReport(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Report - ${document.title}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f5f5f5; font-weight: bold; }
                    .no-print { display: none; }
                    @media print { .no-print { display: none !important; } }
                </style>
            </head>
            <body>
                <h2>Pharma POS Report</h2>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                ${element.innerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S to save forms
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        const activeModal = document.querySelector('.modal.show');
        if (activeModal) {
            const saveBtn = activeModal.querySelector('.btn-gradient, .btn-primary');
            if (saveBtn) saveBtn.click();
        }
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.show');
        if (activeModal) {
            const closeBtn = activeModal.querySelector('.btn-close, .btn-secondary');
            if (closeBtn) closeBtn.click();
        }
    }
});

// Mobile menu toggle
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// Add mobile menu button for responsive design
if (window.innerWidth <= 768) {
    const header = document.querySelector('.page-header');
    if (header) {
        const menuBtn = document.createElement('button');
        menuBtn.className = 'btn btn-light me-3';
        menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
        menuBtn.onclick = toggleSidebar;
        header.insertBefore(menuBtn, header.firstChild);
    }
}

// Auto-refresh for dashboard pages
if (window.location.pathname.includes('dashboard')) {
    // Refresh every 5 minutes
    setTimeout(() => {
        if (document.visibilityState === 'visible') {
            location.reload();
        }
    }, 300000);
}

// Form auto-save for drafts (optional enhancement)
function enableAutoSave(formId, storageKey) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    // Load saved data
    const savedData = localStorage.getItem(storageKey);
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) field.value = data[key];
            });
        } catch (e) {
            console.warn('Failed to load saved form data');
        }
    }
    
    // Save on input
    form.addEventListener('input', function() {
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        localStorage.setItem(storageKey, JSON.stringify(data));
    });
    
    // Clear on successful submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(storageKey);
    });
}