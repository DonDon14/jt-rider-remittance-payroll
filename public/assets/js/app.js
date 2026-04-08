(() => {
    const remittanceForm = document.querySelector('[data-remittance-form]');
    if (remittanceForm) {
        const totalField = remittanceForm.querySelector('[data-total-remitted]');
        const cashTotalField = remittanceForm.querySelector('[data-cash-total]');
        const statusField = remittanceForm.querySelector('[data-variance-status]');
        const supposedField = remittanceForm.querySelector('[data-supposed-remittance]');
        const cashField = remittanceForm.querySelector('[data-cash-remitted]');
        const gcashField = remittanceForm.querySelector('[data-gcash-remitted]');

        const recalculate = () => {
            let denomTotal = 0;
            remittanceForm.querySelectorAll('[data-denom]').forEach((input) => {
                const count = Number(input.value || 0);
                const value = Number(input.dataset.value || 0);
                denomTotal += count * value;
            });

            const supposedRaw = supposedField?.value ?? '';
            const cash = cashField && cashField.value !== '' ? Number(cashField.value || 0) : denomTotal;
            const gcash = Number(gcashField?.value || 0);
            const remitted = cash + gcash;

            if (cashTotalField) {
                cashTotalField.textContent = `PHP ${denomTotal.toFixed(2)}`;
            }

            if (totalField) {
                totalField.textContent = `PHP ${remitted.toFixed(2)}`;
            }

            if (!statusField) {
                return;
            }

            if (supposedRaw === '') {
                statusField.textContent = 'PENDING EXPECTED TOTAL';
                statusField.className = 'badge text-bg-secondary';
                return;
            }

            const supposed = Number(supposedRaw || 0);
            const variance = remitted - supposed;

            if (Math.abs(variance) < 0.005) {
                statusField.textContent = 'BALANCED';
                statusField.className = 'badge badge-balanced';
            } else if (variance < 0) {
                statusField.textContent = `SHORT by PHP ${Math.abs(variance).toFixed(2)}`;
                statusField.className = 'badge badge-short';
            } else {
                statusField.textContent = `OVER by PHP ${Math.abs(variance).toFixed(2)}`;
                statusField.className = 'badge badge-over';
            }
        };

        remittanceForm.addEventListener('input', recalculate);
        recalculate();

        if (window.location.search.includes('modal=1') && document.querySelector('.alert-success') && window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'remittance-saved' }, '*');
        }
    }

    document.querySelectorAll('[data-delivery-form]').forEach((form) => {
        const allocatedField = form.querySelector('[data-allocated]');
        const successfulField = form.querySelector('[data-successful]');
        const failedField = form.querySelector('[data-failed]');
        const riderSelect = form.querySelector('[data-delivery-rider]');
        const commissionField = form.querySelector('[data-delivery-commission]');
        if (!allocatedField || !successfulField || !failedField) {
            return;
        }

        const syncFailed = () => {
            const allocated = Number(allocatedField.value || 0);
            const successful = Number(successfulField.value || 0);

            if (successful > allocated) {
                successfulField.setCustomValidity('Successful deliveries cannot exceed allocated parcels.');
            } else {
                successfulField.setCustomValidity('');
            }

            failedField.value = String(Math.max(0, allocated - successful));
        };

        const syncCommission = () => {
            if (!riderSelect || !commissionField) {
                return;
            }

            const selectedOption = riderSelect.options[riderSelect.selectedIndex];
            const commission = selectedOption?.dataset?.commission;
            if (commission) {
                commissionField.value = commission;
            }
        };

        allocatedField.addEventListener('input', syncFailed);
        successfulField.addEventListener('input', syncFailed);
        riderSelect?.addEventListener('change', syncCommission);
        syncFailed();
        syncCommission();
    });

    document.querySelectorAll('[data-adjustment-form]').forEach((form) => {
        const scopeInputs = form.querySelectorAll('[data-adjustment-scope]');
        const riderGroup = form.querySelector('[data-adjustment-rider-group]');
        const riderSelect = form.querySelector('[data-adjustment-rider-select]');
        if (!scopeInputs.length || !riderGroup || !riderSelect) {
            return;
        }

        const syncScope = () => {
            const selected = form.querySelector('[data-adjustment-scope]:checked');
            const isAll = selected?.value === 'ALL';

            riderGroup.classList.toggle('d-none', isAll);
            riderSelect.disabled = isAll;
            riderSelect.required = !isAll;
            if (isAll) {
                riderSelect.value = '';
            }
        };

        scopeInputs.forEach((input) => input.addEventListener('change', syncScope));
        syncScope();
    });

    document.querySelectorAll('[data-searchable-select]').forEach((wrapper) => {
        const input = wrapper.querySelector('[data-search-target]');
        const select = wrapper.querySelector('[data-search-source]');
        if (!input || !select) {
            return;
        }

        wrapper.classList.add('searchable-select-enhanced');
        select.classList.add('searchable-select-native');

        let results = wrapper.querySelector('[data-search-results]');
        if (!results) {
            results = document.createElement('div');
            results.className = 'searchable-select-results list-group shadow-sm';
            results.setAttribute('data-search-results', '');
            input.insertAdjacentElement('afterend', results);
        }

        const originalOptions = Array.from(select.options)
            .map((option) => ({
                value: option.value,
                text: option.text,
                selected: option.selected,
                commission: option.dataset.commission || '',
            }));

        const syncSelection = (value, updateInput = false) => {
            select.value = value;
            const selected = originalOptions.find((option) => option.value === value);
            if (updateInput && selected && value !== '') {
                input.value = selected.text;
            }
            select.dispatchEvent(new Event('change'));
        };

        const renderOptions = (query) => {
            const normalized = query.trim().toLowerCase();
            const matches = originalOptions.filter((option) => option.value !== '' && (normalized === '' || option.text.toLowerCase().includes(normalized)));
            const activeValue = select.value;

            results.innerHTML = '';
            results.classList.toggle('d-none', matches.length === 0);

            if (matches.length === 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'searchable-select-empty';
                emptyState.textContent = 'No riders matched your search.';
                results.appendChild(emptyState);
                return;
            }

            matches.slice(0, 8).forEach((optionData) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action searchable-select-option';
                button.textContent = optionData.text;
                if (optionData.value === activeValue) {
                    button.classList.add('active');
                }
                button.addEventListener('click', () => {
                    syncSelection(optionData.value, true);
                    renderOptions(input.value);
                    input.focus();
                });
                results.appendChild(button);
            });
        };

        input.addEventListener('focus', () => renderOptions(input.value));
        input.addEventListener('input', () => {
            if (select.value !== '') {
                syncSelection('', false);
            }
            renderOptions(input.value);
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) {
                results.classList.add('d-none');
            }
        });

        const modal = wrapper.closest('.modal');
        if (modal) {
            modal.addEventListener('show.bs.modal', () => {
                input.value = '';
                syncSelection('', false);
                renderOptions('');
            });
        }

        renderOptions('');
        results.classList.add('d-none');
    });

    document.querySelectorAll('[data-modal-frame]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const targetSelector = trigger.getAttribute('data-bs-target');
            const modal = targetSelector ? document.querySelector(targetSelector) : null;
            if (!modal) {
                return;
            }

            const iframe = modal.querySelector('[data-modal-iframe]');
            const title = modal.querySelector('[data-modal-title-display]');
            const url = trigger.getAttribute('data-modal-url') || 'about:blank';
            const modalTitle = trigger.getAttribute('data-modal-title') || 'Details';

            if (iframe) {
                iframe.setAttribute('src', url);
            }

            if (title) {
                title.textContent = modalTitle;
            }
        });
    });

    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('hidden.bs.modal', () => {
            const iframe = modal.querySelector('[data-modal-iframe]');
            if (iframe) {
                iframe.setAttribute('src', 'about:blank');
            }
        });
    });


    const body = document.body;
    const navGroups = Array.from(document.querySelectorAll('[data-nav-group]'));
    const applySidebarState = () => {
        if (!body.classList.contains('admin-shell')) {
            return;
        }

        const saved = window.localStorage.getItem('admin-sidebar-collapsed');
        if (saved === '1' && window.innerWidth >= 992) {
            body.classList.add('is-sidebar-collapsed');
        }

        navGroups.forEach((group) => {
            const key = group.getAttribute('data-nav-group-key') || '';
            const defaultOpen = group.getAttribute('data-default-open') === '1';
            const savedState = key ? window.localStorage.getItem(`admin-nav-group-${key}`) : null;
            const isOpen = savedState === null ? defaultOpen : savedState === '1';
            group.classList.toggle('is-open', isOpen);
            const button = group.querySelector('[data-nav-group-toggle]');
            button?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    };

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!body.classList.contains('admin-shell')) {
                return;
            }

            if (window.innerWidth < 992) {
                body.classList.toggle('is-sidebar-open');
                return;
            }

            body.classList.toggle('is-sidebar-collapsed');
            window.localStorage.setItem('admin-sidebar-collapsed', body.classList.contains('is-sidebar-collapsed') ? '1' : '0');
        });
    });


    document.querySelectorAll('[data-nav-group-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            if (body.classList.contains('is-sidebar-collapsed') && window.innerWidth >= 992) {
                return;
            }

            const group = button.closest('[data-nav-group]');
            if (!group) {
                return;
            }

            const isOpen = group.classList.toggle('is-open');
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            const key = group.getAttribute('data-nav-group-key');
            if (key) {
                window.localStorage.setItem(`admin-nav-group-${key}`, isOpen ? '1' : '0');
            }
        });
    });
    document.addEventListener('click', (event) => {
        if (window.innerWidth >= 992 || !body.classList.contains('admin-shell') || !body.classList.contains('is-sidebar-open')) {
            return;
        }

        const sidebar = document.querySelector('[data-admin-sidebar]');
        if (!sidebar || sidebar.contains(event.target) || event.target.closest('[data-sidebar-toggle]')) {
            return;
        }

        body.classList.remove('is-sidebar-open');
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            body.classList.remove('is-sidebar-open');
        }
    });

    applySidebarState();
    window.addEventListener('message', (event) => {
        if (!event.data || event.data.type !== 'remittance-saved') {
            return;
        }

        const modalElement = document.querySelector('#remittanceModal');
        if (modalElement) {
            const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modalInstance.hide();
        }

        window.location.reload();
    });
})();







