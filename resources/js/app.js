document.addEventListener("DOMContentLoaded", () => {
    const errors = document.getElementById("form-errors");
    if (errors) {
        errors.focus();
        errors.addEventListener("click", (event) => {
            const link = event.target.closest('a[href^="#"]');
            const field = link && document.getElementById(link.hash.slice(1));
            if (!field) return;
            for (let parent = field.parentElement; parent; parent = parent.parentElement) {
                if (parent.tagName === "DETAILS") parent.open = true;
            }
            field.focus();
        });
    }

    const sidebar = document.getElementById("sidebar");
    const menu = document.querySelector("[data-menu]");
    const skipLink = document.querySelector('a[href="#main"]');
    const closeButton = document.querySelector("[data-sidebar-close]");
    const overlay = document.querySelector("[data-sidebar-overlay]");
    const compactButton = document.querySelector("[data-sidebar-compact]");
    const desktop = window.matchMedia("(min-width:1024px)");
    const storageKey = "markethub.sidebar.v1";
    let preferences = {};
    try {
        preferences = JSON.parse(localStorage.getItem(storageKey) || "{}") || {};
        if (typeof preferences !== "object" || Array.isArray(preferences)) preferences = {};
    } catch (_error) {
        preferences = {};
    }
    const save = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(preferences));
        } catch (_error) {
            /* Private browsing may disable storage. */
        }
    };
    const groups = [...document.querySelectorAll("[data-nav-group]")];
    const restoreGroups = () =>
        groups.forEach((group) => {
            const active = !!group.querySelector('[aria-current="page"]');
            group.open = active || preferences[group.dataset.navGroup] === true;
            group.querySelector("summary").setAttribute("aria-expanded", String(group.open));
        });
    restoreGroups();
    const setCompact = (compact) => {
        document.body.classList.toggle("sidebar-compact", compact);
        if (compact && desktop.matches) {
            groups.forEach((group) => (group.open = true));
        } else restoreGroups();
        if (compactButton) {
            compactButton.setAttribute("aria-pressed", String(compact));
            compactButton.setAttribute(
                "aria-label",
                compact ? "Expand sidebar" : "Collapse sidebar",
            );
        }
    };
    setCompact(preferences.compact === true);
    if (compactButton)
        compactButton.addEventListener("click", () => {
            preferences.compact = !document.body.classList.contains("sidebar-compact");
            setCompact(preferences.compact);
            save();
        });
    const content = sidebar ? sidebar.nextElementSibling : null;
    const canOpenDrawer = sidebar && menu && closeButton && overlay && content &&
        "inert" in HTMLElement.prototype && typeof desktop.addEventListener === "function";
    const closeSidebar = () => {
        if (content) content.inert = false;
        if (skipLink) skipLink.inert = false;
        if (sidebar) {
            sidebar.removeAttribute("role");
            sidebar.removeAttribute("aria-modal");
        }
        document.body.classList.remove("sidebar-open");
        if (overlay) overlay.hidden = true;
        if (menu) menu.setAttribute("aria-expanded", "false");
    };
    if (canOpenDrawer)
        menu.addEventListener("click", () => {
            if (desktop.matches) return;
            document.body.classList.add("sidebar-open");
            overlay.hidden = false;
            menu.setAttribute("aria-expanded", "true");
            sidebar.setAttribute("role", "dialog");
            sidebar.setAttribute("aria-modal", "true");
            closeButton.focus();
            content.inert = true;
            if (skipLink) skipLink.inert = true;
        });
    if (closeButton)
        closeButton.addEventListener("click", () => {
            closeSidebar();
            if (menu) menu.focus();
        });
    if (overlay)
        overlay.addEventListener("click", () => {
            closeSidebar();
            if (menu) menu.focus();
        });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Tab" && document.body.classList.contains("sidebar-open")) {
            const focusable = [...sidebar.querySelectorAll("a,button,summary")].filter(
                (el) => el.getClientRects().length,
            );
            const first = focusable[0],
                last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
        if (event.key === "Escape" && document.body.classList.contains("sidebar-open")) {
            closeSidebar();
            if (menu) menu.focus();
        }
    });
    if (sidebar)
        sidebar.querySelectorAll("a[href]").forEach((link) =>
            link.addEventListener("click", () => {
                if (!desktop.matches) closeSidebar();
            }),
        );
    if (canOpenDrawer) {
        desktop.addEventListener("change", () => {
            const focusWasInSidebar = sidebar.contains(document.activeElement) ||
                document.body.classList.contains("sidebar-open");
            const focusWasOnToggle = document.activeElement === (desktop.matches ? menu : compactButton);
            closeSidebar();
            setCompact(preferences.compact === true);
            if (focusWasInSidebar || focusWasOnToggle) (desktop.matches ? compactButton : menu)?.focus();
        });
        document.body.classList.add("sidebar-enhanced");
    }
    groups.forEach((group) => {
        const summary = group.querySelector("summary");
        const panel = group.querySelector(".nav-group-items");
        let busy = false;
        summary.addEventListener("click", (event) => {
            event.preventDefault();
            if (busy || (desktop.matches && document.body.classList.contains("sidebar-compact")))
                return;
            const opening = !group.open;
            const animate = typeof panel.animate === "function" &&
                !window.matchMedia("(prefers-reduced-motion:reduce)").matches;
            if (opening) group.open = true;
            summary.setAttribute("aria-expanded", String(opening));
            preferences[group.dataset.navGroup] = opening;
            save();
            if (!animate) {
                group.open = opening;
                return;
            }
            busy = true;
            const height = panel.scrollHeight;
            const animation = panel.animate(
                [
                    { height: opening ? "0px" : height + "px" },
                    { height: opening ? height + "px" : "0px" },
                ],
                { duration: 200, easing: "ease" },
            );
            animation.finished.then(
                () => {
                    group.open = opening;
                    busy = false;
                },
                () => {
                    busy = false;
                },
            );
        });
    });
    document.querySelectorAll('[data-clear-section-filter]').forEach((marketFilter) => {
        const sectionFilter = document.getElementById(marketFilter.dataset.clearSectionFilter);
        marketFilter.addEventListener('change', () => { sectionFilter.value = ''; });
    });

    const picker = document.querySelector('[data-section-picker]');
    if (picker) {
        const market = document.getElementById('market_id');
        const select = document.getElementById('market_section_id');
        const search = picker.querySelector('input[type="search"]');
        const refresh = picker.querySelector('[data-refresh-sections]');
        const add = picker.querySelector('[data-add-section]');
        const status = picker.querySelector('[role="status"]');
        const retained = select.querySelector('[data-retained-market]');
        const empty = select.options[0];
        let options = [...select.options].slice(1);
        let requestNumber = 0;
        picker.querySelector('[data-section-search-wrap]').hidden = false;
        refresh.hidden = false;
        select.required = true;

        const renderOptions = () => {
            const terms = search.value.trim().toLocaleLowerCase();
            const selected = select.value;
            const matches = options.filter((option) => option.text.toLocaleLowerCase().includes(terms));
            const kept = options.find((option) => option.value === selected);
            if (kept && !matches.includes(kept)) matches.unshift(kept);
            select.replaceChildren(empty, ...matches);
            if (matches.some((option) => option.value === selected)) select.value = selected;
            else select.value = '';
            if (!market.value) status.textContent = 'Choose a market to see its sections.';
            else if (!matches.length) status.textContent = terms ? 'No sections match your search.' : 'This market has no active sections.';
            else if (select.selectedOptions[0] === retained) status.textContent = 'The current inactive section is retained. Choose an active section to move the stall.';
            else status.textContent = '';
        };
        const updateShortcut = () => {
            if (!add) return;
            const url = new URL(add.dataset.createUrl);
            url.searchParams.set('from_stall', '1');
            if (market.value) url.searchParams.set('market_id', market.value);
            add.href = url.toString();
        };
        const loadOptions = async (changingMarket) => {
            const currentRequest = ++requestNumber;
            const currentMarket = market.value;
            const previous = changingMarket ? '' : select.value;
            const originalOptions = changingMarket ? [] : options;
            updateShortcut();
            const retainCurrent = retained && retained.dataset.retainedMarket === currentMarket;
            options = retainCurrent ? [retained] : [];
            select.replaceChildren(empty);
            search.value = '';
            if (!currentMarket) {
                select.disabled = search.disabled = refresh.disabled = true;
                renderOptions();
                return;
            }
            select.disabled = search.disabled = refresh.disabled = true;
            status.textContent = 'Loading sections…';
            let failed = false;
            try {
                const response = await fetch(picker.dataset.optionsUrl.replace('MARKET_ID', encodeURIComponent(currentMarket)), {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('Section options unavailable');
                const sections = await response.json();
                if (currentRequest !== requestNumber) return;
                options = sections.map((section) => new Option(`${section.name} · ${section.code}`, section.id));
                if (retainCurrent && !options.some((option) => option.value === retained.value)) options.push(retained);
            } catch (_error) {
                if (currentRequest !== requestNumber) return;
                options = originalOptions.length ? originalOptions : options;
                failed = true;
            }
            select.replaceChildren(empty, ...options);
            select.value = options.some((option) => option.value === previous) ? previous : '';
            select.disabled = search.disabled = refresh.disabled = false;
            renderOptions();
            if (failed) status.textContent = 'Could not refresh sections. Try again.';
        };
        market.addEventListener('change', () => loadOptions(true));
        refresh.addEventListener('click', () => loadOptions(false));
        search.addEventListener('input', renderOptions);
        select.addEventListener('change', renderOptions);
        select.disabled = search.disabled = refresh.disabled = !market.value;
        updateShortcut();
        renderOptions();
    }

    const ordering = document.querySelector('[data-section-order]');
    if (ordering) {
        const list = ordering.querySelector('[data-section-order-list]');
        const status = ordering.querySelector('[data-section-order-status]');
        const updateButtons = () => {
            for (const row of list.children) {
                row.querySelector('[data-order-move="up"]').disabled = !row.previousElementSibling;
                row.querySelector('[data-order-move="down"]').disabled = !row.nextElementSibling;
            }
        };
        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-order-move]');
            if (!button) return;
            const row = button.closest('li');
            if (button.dataset.orderMove === 'up') list.insertBefore(row, row.previousElementSibling);
            else list.insertBefore(row.nextElementSibling, row);
            updateButtons();
            (button.disabled ? row.querySelector('[data-order-move]:enabled') : button).focus();
            status.textContent = 'Order changed. Save order to apply it.';
        });
        updateButtons();
        ordering.hidden = false;
    }

    let controller;
    const stallLinks = document.querySelectorAll("[data-stall-url]");
    stallLinks.forEach((button) => {
        if (typeof fetch !== "function" || typeof AbortController !== "function") return;
        const status = document.getElementById("stall-detail-status");
        button.setAttribute("role", "button");
        button.setAttribute("aria-pressed", "false");
        button.addEventListener("keydown", (event) => {
            if (event.key === " ") event.preventDefault();
        });
        button.addEventListener("keyup", (event) => {
            if (event.key === " ") button.click();
        });
        button.addEventListener("click", async (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            const target = document.getElementById("stall-detail");
            if (controller) controller.abort();
            const request = new AbortController();
            controller = request;
            target.setAttribute("aria-busy", "true");
            target.textContent = "Loading stall details…";
            status.textContent = "Loading stall details…";
            stallLinks.forEach((link) => link.setAttribute("aria-pressed", String(link === button)));
            try {
                const response = await fetch(button.dataset.stallUrl, {
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                    signal: request.signal,
                });
                if (controller !== request) return;
                if (response.redirected) {
                    location.href = response.url;
                    return;
                }
                if (!response.ok) throw Error("Stall details unavailable");
                const html = await response.text();
                if (controller !== request) return;
                target.innerHTML = html;
                target.setAttribute("aria-busy", "false");
                status.textContent = "";
                target.classList.add("details-loaded");
                const heading = target.querySelector("h2");
                if (heading) {
                    heading.tabIndex = -1;
                    heading.focus({ preventScroll: true });
                }
                target.scrollIntoView({ block: "nearest" });
            } catch (error) {
                if (controller !== request || error.name === "AbortError") return;
                target.textContent = "Unable to load this stall. Please try again.";
                status.textContent = target.textContent;
            } finally {
                if (controller === request) target.setAttribute("aria-busy", "false");
            }
        });
    });
    const confirming = new WeakSet();
    const resumed = new WeakSet();
    document.addEventListener("submit", async (e) => {
        const form = e.target;
        const submitter = e.submitter;
        const message = submitter?.dataset.confirm || form.dataset.confirm;
        if (message && !resumed.has(form)) {
            e.preventDefault();
            if (confirming.has(form)) return;
            confirming.add(form);
            try {
                const confirmed = window.Swal ? (await Swal.fire({
                    titleText: message,
                    text: "This action will be recorded with your account.",
                    icon: "question",
                    showCancelButton: true,
                    focusCancel: true,
                    confirmButtonText: "Confirm",
                    confirmButtonColor: "#1d67d7",
                })).isConfirmed : window.confirm(message);
                if (confirmed) {
                    resumed.add(form);
                    form.requestSubmit(submitter || undefined);
                }
            } finally {
                resumed.delete(form);
                confirming.delete(form);
            }
            return;
        }
        // Disabled submit buttons are excluded from the browser's form data.
        form.querySelector("[data-submit-intent]")?.remove();
        if (e.submitter && e.submitter.name) {
            const intent = document.createElement("input");
            intent.type = "hidden";
            intent.name = e.submitter.name;
            intent.value = e.submitter.value;
            intent.dataset.submitIntent = "true";
            form.appendChild(intent);
        }
        form.querySelectorAll("button[type=submit],button:not([type])").forEach((b) => {
            b.disabled = true;
            b.textContent = "Saving…";
        });
    });
    const node = document.getElementById("chart-data");
    if (node && window.Chart) {
        const data = JSON.parse(node.textContent);
        for (const [id, spec] of Object.entries(data)) {
            const canvas = document.getElementById(id);
            if (canvas)
                new Chart(canvas, {
                    type: spec.type || "bar",
                    data: {
                        labels: spec.labels,
                        datasets: [
                            {
                                label: spec.money === false ? "Stalls" : "PHP",
                                data: spec.values,
                                backgroundColor: spec.colors || "#1d67d7",
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: spec.type === "doughnut" } },
                        scales: spec.type === "doughnut" ? {} : { y: { beginAtZero: true } },
                    },
                });
        }
    }
    document
        .querySelectorAll("[data-print]")
        .forEach((b) => b.addEventListener("click", () => window.print()));
});
