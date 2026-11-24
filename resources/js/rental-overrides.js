document.querySelectorAll("[data-rental-override]").forEach((form) => {
    const method = form.elements.override_type;
    const start = form.elements.effective_from;
    const preview = form.querySelector("[data-override-preview]");
    const result = form.querySelector("[data-override-result]");
    const submitSaved = form.parentElement.querySelector("[data-submit-saved-draft]");
    let pending;

    function updateFields() {
        pending?.abort();
        for (const group of form.querySelectorAll("[data-override-amount]")) {
            const selected = group.dataset.overrideAmount === method.value;
            const input = group.querySelector("input");
            group.hidden = !selected;
            input.disabled = !selected;
            input.required = selected;
        }
        preview.disabled = false;
        result.replaceChildren();
    }

    function line(message, style = "mt-2") {
        const paragraph = document.createElement("p");
        paragraph.className = style;
        paragraph.textContent = message;
        result.append(paragraph);
    }

    method.addEventListener("change", updateFields);
    form.querySelectorAll("[data-override-amount] input").forEach((input) => {
        input.addEventListener("input", updateFields);
    });
    start.addEventListener("input", updateFields);
    updateFields();
    preview.hidden = false;
    if (submitSaved) {
        form.addEventListener("input", () => { submitSaved.disabled = true; });
        form.addEventListener("change", () => { submitSaved.disabled = true; });
    }
    preview.addEventListener("click", async () => {
        const field = method.value === "daily_amount" ? "override_daily_amount" : "override_rate_per_sqm";
        const amount = form.elements[field];
        if (!amount.reportValidity() || !start.reportValidity()) return;
        if (!start.value.endsWith("-01")) {
            result.replaceChildren();
            line("Choose the first day of a month before previewing.", "mt-2 text-red-800");
            start.focus();
            return;
        }

        pending?.abort();
        const active = new AbortController();
        pending = active;
        result.replaceChildren();
        line("Calculating proposed rent…");
        preview.disabled = true;
        const period = start.value.slice(0, 7);
        try {
            const response = await fetch(form.dataset.previewUrl, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": form.elements._token.value,
                },
                body: JSON.stringify({period, override_type: method.value, [field]: amount.value}),
                signal: active.signal,
            });
            const data = await response.json();
            if (active.signal.aborted) return;
            result.replaceChildren();
            if (!response.ok) {
                line(data.errors ? Object.values(data.errors).flat().join(" ") : "Preview unavailable. Reload the page or try again.", "mt-2 text-red-800");
                return;
            }

            line(`Proposed rent for ${period} · Approval required`, "mt-3 font-semibold");
            line(`Automatic PHP ${data.automatic_daily_rental} daily · Proposed PHP ${data.daily_rental} daily · Difference PHP ${data.rental_difference} daily`);
            line(`Proposed monthly total with CUSA: PHP ${data.total_monthly_rental_cusa} for ${data.billing_days} days.`);
            if (data.zero_rent || data.out_of_range) {
                line("This proposal needs explicit exception authority and supporting references. CUSA remains payable.", "mt-2 text-amber-900");
            }
            if (!data.contract_rate_consistent) {
                line("The two-decimal equivalent rate cannot reproduce this daily amount in the lease template.", "mt-2 text-amber-900");
            }
        } catch (error) {
            if (active.signal.aborted) return;
            result.replaceChildren();
            line("Preview unavailable. Reload the page or try again.", "mt-2 text-red-800");
        } finally {
            if (pending === active && !active.signal.aborted) preview.disabled = false;
        }
    });
});
