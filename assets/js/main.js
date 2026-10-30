/**
 * Ledger - client-side scripts
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize category dropdown filtering for quick modal
    filterCategoriesByType('quick_type', 'quick_category_id');

    // Auto dismiss flash notification if present
    const flashAlert = document.getElementById('flashAlert');
    if (flashAlert) {
        setTimeout(() => {
            flashAlert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            flashAlert.style.opacity = '0';
            flashAlert.style.transform = 'translateY(-6px)';
            setTimeout(() => flashAlert.remove(), 400);
        }, 5500);
    }

    // Close modals on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeQuickAddModal();
            closeConfirmModal();
            const genericModal = document.querySelector('.modal-overlay.active');
            if (genericModal) genericModal.classList.remove('active');
        }
    });

    // Close modals on overlay backdrop click
    document.querySelectorAll('.modal-overlay').forEach((overlay) => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });
});

/**
 * Filters the category select options based on the chosen transaction type (income / expense).
 */
function filterCategoriesByType(typeSelectId, catSelectId) {
    const typeSelect = document.getElementById(typeSelectId);
    const catSelect = document.getElementById(catSelectId);

    if (!typeSelect || !catSelect) return;

    const selectedType = typeSelect.value;
    let hasValidSelection = false;

    Array.from(catSelect.options).forEach((opt) => {
        const catType = opt.getAttribute('data-type');
        if (!catType) {
            opt.style.display = '';
            return;
        }

        if (catType === selectedType) {
            opt.style.display = '';
            opt.disabled = false;
            if (opt.selected) hasValidSelection = true;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
            if (opt.selected) opt.selected = false;
        }
    });

    if (!hasValidSelection) {
        catSelect.value = '';
    }
}

/**
 * Universal Quick Add Modal open / close
 */
function openQuickAddModal() {
    const modal = document.getElementById('quickAddModal');
    if (modal) {
        modal.classList.add('active');
        filterCategoriesByType('quick_type', 'quick_category_id');
        setTimeout(() => {
            const amountInput = document.getElementById('quick_amount');
            if (amountInput) amountInput.focus();
        }, 100);
    }
}

function closeQuickAddModal() {
    const modal = document.getElementById('quickAddModal');
    if (modal) modal.classList.remove('active');
}

/**
 * Universal Safe Deletion Confirmation Dialog
 */
function openConfirmDelete(actionUrl, id, message) {
    const modal = document.getElementById('confirmModal');
    const form = document.getElementById('confirmForm');
    const idInput = document.getElementById('confirmId');
    const msgEl = document.getElementById('confirmMessage');

    if (modal && form && idInput) {
        form.action = actionUrl;
        idInput.value = id;
        if (message && msgEl) {
            msgEl.textContent = message;
        }
        modal.classList.add('active');
    }
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) modal.classList.remove('active');
}
