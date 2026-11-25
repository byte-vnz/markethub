(() => {
    const form = document.querySelector('[data-registration]');
    if (!form) return;

    const businesses = form.querySelector('[data-businesses]');
    const assignments = form.querySelector('[data-assignments]');
    const helpers = form.querySelector('[data-pending-helpers]');
    const previews = form.querySelector('[data-previews]');
    const addBusiness = form.querySelector('[data-add-business]');
    const addAssignment = form.querySelector('[data-add-assignment]');
    const confirm = form.querySelector('[data-confirm-registration]');
    const review = form.querySelector('[data-review]');
    const requests = new Map();
    const previewUrl = form.dataset.previewUrl;
    let nextBusiness = 0;
    let nextAssignment = 0;

    function appendTemplate(target, selector, placeholder, key) {
        const fragment = form.querySelector(selector).content.cloneNode(true);
        fragment.querySelectorAll('*').forEach(element => {
            for (const attribute of element.attributes) {
                if (attribute.value.includes(placeholder)) {
                    attribute.value = attribute.value.replaceAll(placeholder, key);
                }
            }
        });
        const row = fragment.firstElementChild;
        target.append(fragment);
        return row;
    }

    function update() {
        const registered = new Map();
        for (const row of businesses.children) {
            const name = row.querySelector('[data-business-name]').value.trim();
            const category = row.querySelector('[data-business-category]');
            const categoryLabel = category.value ? category.selectedOptions[0].label : '';
            const label = [name || 'Individual stallholder', categoryLabel].filter(Boolean).join(' · ');
            registered.set(row.dataset.registrationBusiness, { row, category, label });
            row.querySelector('[data-business-heading]').textContent = name || 'Business details';
        }
        addBusiness.disabled = registered.size >= 10;
        if (addAssignment) {
            addAssignment.disabled = registered.size === 0 || assignments.children.length >= 10;
            form.querySelector('[data-add-assignment-help]').textContent = registered.size === 0 ? 'Add a business before selecting a stall.' : '';
        }
        if (!previewUrl) {
            confirm.disabled = assignments.children.length > 0;
            form.querySelector('[data-confirm-restriction]').hidden = !confirm.disabled;
        }

        const stallLabels = [];
        for (const row of assignments.children) {
            const select = row.querySelector('[data-linked-business]');
            const key = select.value;
            select.replaceChildren(new Option('Choose a business', ''));
            for (const [businessKey, business] of registered) {
                select.append(new Option(business.label, businessKey));
            }
            select.value = registered.has(key) ? key : '';
            const stall = row.querySelector('[data-assignment-stall]').selectedOptions[0];
            const stallLabel = stall.dataset.label || 'Stall selection';
            row.querySelector('[data-assignment-heading]').textContent = stallLabel;
            row.querySelector('[data-stall-basis]').textContent = stall.value
                ? `${titleCase(stall.dataset.location || 'Unclassified')} · ${stall.dataset.area} sq. m.` : '';
            const helperRow = [...helpers.children].find(item => item.dataset.assignmentHelpers === row.dataset.registrationAssignment);
            helperRow.querySelector('[data-helper-heading]').textContent = `Helpers for ${stall.value ? stallLabel : 'unselected stall'}`;
            if (stall.value) stallLabels.push(stallLabel);
            if (previewUrl) refreshPreview(row, registered.get(select.value), stallLabel);
        }
        if (previews) form.querySelector('[data-preview-empty]').hidden = Boolean(assignments.children.length);

        const first = form.elements.namedItem('first_name').value.trim();
        const last = form.elements.namedItem('last_name').value.trim();
        const person = [first, last].filter(Boolean).join(' ');
        review.replaceChildren(
            paragraph(person || 'Enter the stallholder’s name.'),
            paragraph(`${registered.size} ${registered.size === 1 ? 'business' : 'businesses'} · ${assignments.children.length} ${assignments.children.length === 1 ? 'stall selection' : 'stall selections'}`),
        );
        if (stallLabels.length) review.append(paragraph(stallLabels.join(', ')));
    }

    function titleCase(value) {
        return value ? value[0].toUpperCase() + value.slice(1) : '';
    }

    function paragraph(content) {
        const element = document.createElement('p');
        element.textContent = content;
        return element;
    }

    function showPreview(container, label, message, amounts) {
        const heading = document.createElement('h3');
        heading.textContent = label;
        container.replaceChildren(heading, paragraph(message));
        if (!amounts) return;
        const detail = document.createElement('dl');
        const money = key => `PHP ${amounts[key]}`;
        const fixedDays = amounts.billing_cycle === 'fixed_days';
        const lines = [
            ['Floor area', `${amounts.floor_area} sq. m.`],
            ['Minimum / maximum per sq. m.', `${money('minimum_rate')} / ${money('maximum_rate')}`],
            ['Applied rate per sq. m.', `${money('rental_rate_sqm')} (${titleCase(amounts.location_type)})`],
            ['Daily rental', money('daily_rental')],
            ['Billing days', fixedDays ? String(amounts.billing_days) : `${amounts.billing_days} (${amounts.billing_day_rule})`],
            ...(fixedDays ? [
                ['Daily CUSA for records only', money('daily_cusa')],
                ['CUSA for the period (records only)', money('monthly_cusa')],
                ['Period rent payable', money('total_monthly_rental_cusa')],
            ] : [
                ['Daily CUSA', money('daily_cusa')],
                ['Monthly rental', money('monthly_rental')],
                ['Monthly CUSA', money('monthly_cusa')],
                ['Monthly total', money('total_monthly_rental_cusa')],
                ['Two months’ advance', money('advance_rental')],
                ['Security deposit', money('security_deposit')],
                ['Advance and deposit total', money('total_initial_payment')],
            ]),
        ];
        for (const [label, amount] of lines) {
            const pair = document.createElement('div');
            pair.className = 'detailrow';
            const term = document.createElement('dt');
            term.textContent = label;
            const definition = document.createElement('dd');
            definition.textContent = amount;
            pair.append(term, definition);
            detail.append(pair);
        }
        container.append(detail);
        if (!fixedDays && !amounts.advance_deposit_authorized) {
            container.append(paragraph('Advance and deposit are estimates. The market policy does not authorize their collection.'));
        }
    }

    function refreshPreview(row, business, label) {
        const key = row.dataset.registrationAssignment;
        let state = requests.get(key);
        if (!state) {
            const container = document.createElement('section');
            container.className = 'border-t border-slate-200 py-3 text-sm';
            previews.append(container);
            state = { container };
            requests.set(key, state);
        }
        const payload = {
            tenant_id: form.dataset.tenant || null,
            business_id: business?.row.querySelector('[data-registration-business-id]')?.value || null,
            business_category_id: business?.category.value || null,
            stall_id: row.querySelector('[data-assignment-stall]').value,
            start_date: row.querySelector('[data-assignment-start]').value,
            end_date: row.querySelector('[data-assignment-end]').value || null,
        };
        const interval = row.querySelector('[data-billing-days]');
        if (interval) {
            payload.billing_interval_days = interval.value || null;
            payload.daily_cusa = row.querySelector('[data-daily-cusa]').value || null;
        }
        const active = business?.row.querySelector('[data-business-status]').value === 'active';
        const fingerprint = JSON.stringify([payload, active]);
        if (state.fingerprint === fingerprint) return;
        state.fingerprint = fingerprint;
        state.abort?.abort();
        if (!active || !payload.business_category_id || !payload.stall_id || !payload.start_date) {
            showPreview(state.container, label, 'Choose an active business with a category, a stall and a start date.');
            return;
        }
        showPreview(state.container, label, interval ? 'Checking the approved rate and billing interval…' : 'Checking the approved rate and policy…');
        const abort = new AbortController();
        state.abort = abort;
        fetch(previewUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.elements.namedItem('_token').value,
            },
            body: JSON.stringify(payload),
            signal: abort.signal,
        }).then(async response => {
            const result = await response.json();
            if (abort.signal.aborted) return;
            if (response.ok) {
                showPreview(state.container, label, `${result.business_category_name} · ${result.period_label}`, result);
            } else {
                showPreview(state.container, label, Object.values(result.errors || {}).flat()[0] || 'Rental preview is unavailable. Reload the form to try again.');
            }
        }).catch(() => {
            if (!abort.signal.aborted) showPreview(state.container, label, 'Rental preview is unavailable. Change a selection to retry.');
        });
    }

    form.addEventListener('input', event => {
        const field = event.target;
        if (field.dataset.optionSearch) {
            const select = document.getElementById(field.dataset.optionSearch);
            const words = field.value.trim().toLowerCase().split(/\s+/);
            for (const option of select.options) {
                option.hidden = Boolean(option.value && !option.selected && !words.every(word => option.label.toLowerCase().includes(word)));
                option.disabled = option.hidden;
            }
            return;
        }
        if (field.matches('[data-business-name], #first_name, #last_name')) update();
    });

    form.addEventListener('change', event => {
        if (event.target.matches('select, input[type=date], [data-billing-days], [data-daily-cusa]')) update();
    });

    form.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button === addBusiness) {
            let key;
            do { key = `new-${++nextBusiness}`; } while (document.getElementById(`business-${key}-name`));
            const row = appendTemplate(businesses, '[data-business-template]', '__BUSINESS__', key);
            update();
            row.querySelector('input')?.focus();
        } else if (button === addAssignment) {
            let key;
            do { key = `new-${++nextAssignment}`; } while (document.getElementById(`stall-${key}`));
            const row = appendTemplate(assignments, '[data-assignment-template]', '__ASSIGNMENT__', key);
            appendTemplate(helpers, '[data-helpers-template]', '__ASSIGNMENT__', key);
            update();
            row.querySelector('select')?.focus();
        } else if (button.matches('[data-remove-business]')) {
            button.closest('[data-registration-business]').remove();
            update();
            addBusiness.focus();
        } else if (button.matches('[data-remove-assignment]')) {
            const row = button.closest('[data-registration-assignment]');
            const key = row.dataset.registrationAssignment;
            const state = requests.get(key);
            state?.abort?.abort();
            state?.container.remove();
            requests.delete(key);
            [...helpers.children].find(item => item.dataset.assignmentHelpers === key)?.remove();
            row.remove();
            update();
            addAssignment?.focus();
        }
    });

    addBusiness.hidden = false;
    if (addAssignment) addAssignment.hidden = false;
    update();
})();
