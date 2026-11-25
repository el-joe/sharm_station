import { request, escapeHtml } from './http.js';
import { toast } from './toast.js';

/**
 * Admin booking editor (SRS 9.4, 12.3).
 *
 * data-mode="create": new booking for a customer, a new customer or on behalf of a partner (accommodation with several
 * rooms, trip or package with units, add-ons and optional rooms). "Calculate price" posts the payload to the quote
 * endpoint, "Create booking" to the store endpoint.
 * data-mode="edit": change of an existing booking: dates and rooms of a stay; travel date, party (adults, children ages),
 * units, add-ons and optional rooms of a trip or package. "Preview changes" shows the diff and the new price; "Save
 * changes" is enabled once a valid preview matches the form.
 *
 * <form data-booking-editor data-mode data-quote-url data-submit-url data-submit-method [data-options-url]
 *       [data-product-type] [data-building-rooms] [data-room-option-list] [data-current-rows]>
 */
const money = new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' });

function parseAges(text) {
    return String(text ?? '')
        .split(/[,;\s]+/)
        .map((value) => value.trim())
        .filter((value) => value !== '')
        .map((value) => Number.parseInt(value, 10))
        .filter((value) => Number.isInteger(value) && value >= 0);
}

function value(form, name) {
    const field = form.querySelector(`[name="${name}"]`);

    if (!field) {
        return null;
    }

    if (field.type === 'radio') {
        return form.querySelector(`[name="${name}"]:checked`)?.value ?? null;
    }

    return field.value === '' ? null : field.value;
}

export function initBookingEditor() {
    document.querySelectorAll('form[data-booking-editor]').forEach((form) => new BookingEditor(form));
}

class BookingEditor {
    constructor(form) {
        this.form = form;
        this.mode = form.dataset.mode;
        this.result = form.querySelector('[data-result]');
        this.submitButton = form.querySelector('[data-action="submit"]');
        this.roomList = JSON.parse(form.dataset.buildingRooms ?? '[]');
        this.optionList = JSON.parse(form.dataset.roomOptionList ?? '[]');
        this.product = null;
        this.previewed = null;

        form.addEventListener('click', (event) => this.onClick(event));
        form.addEventListener('change', (event) => this.onChange(event));
        form.addEventListener('input', () => this.invalidate());
        form.querySelector('[name="customer_id"]')?.addEventListener('select2:item', (event) => this.fillLead(event.detail));

        if (this.mode === 'edit') {
            const rows = JSON.parse(form.dataset.currentRows ?? '[]');
            const type = form.dataset.productType === 'room' ? 'rooms' : 'room_options';
            rows.forEach((row) => this.addRow(type, row));
        } else {
            this.addRow('rooms');
        }
    }

    productType() {
        return this.mode === 'edit' ? this.form.dataset.productType : value(this.form, 'product_type');
    }

    onClick(event) {
        const add = event.target.closest('[data-add-row]');
        const remove = event.target.closest('[data-remove-row]');
        const action = event.target.closest('[data-action]');

        if (add) {
            this.addRow(add.dataset.addRow);
            this.invalidate();
        } else if (remove) {
            remove.closest('[data-row]').remove();
            this.invalidate();
        } else if (action) {
            action.dataset.action === 'quote' ? this.quote() : this.submit();
        }
    }

    async onChange(event) {
        const key = event.target.dataset?.productKey;
        this.invalidate();

        if (event.target.name === 'product_type') {
            this.form.querySelector('[data-trip-fields]').hidden = event.target.value === 'room';
        }

        if (!key || !event.target.value) {
            return;
        }

        try {
            const url = `${this.form.dataset.optionsUrl}?type=${encodeURIComponent(key)}&key=${encodeURIComponent(event.target.value)}`;
            const { data } = await request(url);

            if (key === 'building') {
                this.roomList = data.rooms;
                this.form.querySelectorAll('[data-rows="rooms"] select').forEach((select) => this.fillSelect(select, this.roomList, 'room'));
            } else {
                this.renderProduct(data);
            }
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    fillLead(customer) {
        if (!customer) {
            return;
        }

        [['lead[name]', customer.name], ['lead[email]', customer.email], ['lead[phone]', customer.phone]].forEach(([name, text]) => {
            const input = this.form.querySelector(`[name="${name}"]`);

            if (input && !input.value && text) {
                input.value = text;
            }
        });
    }

    fillSelect(select, items, kind) {
        const current = select.value;
        select.innerHTML = '<option value="">Choose a room</option>' + items.map((item) => {
            const capacity = kind === 'room'
                ? `${item.room_type} · ${item.max_adults} ad. / ${item.max_children} ch. / max ${item.max_total}${item.max_extra_beds ? ` +${item.max_extra_beds} extra bed` : ''}`
                : `${item.max_adults} ad. / ${item.max_children} ch. / max ${item.max_total}`;

            return `<option value="${item.id}">${escapeHtml(item.name)} (${escapeHtml(capacity)})</option>`;
        }).join('');
        select.value = current;
    }

    addRow(type, values = {}) {
        const template = this.form.querySelector(`template[data-template="${type}"]`);
        const container = this.form.querySelector(`[data-rows="${type}"]`);

        if (!template || !container) {
            return;
        }

        const row = template.content.firstElementChild.cloneNode(true);
        container.append(row);
        const select = row.querySelector('select');
        this.fillSelect(select, type === 'rooms' ? this.roomList : this.optionList, type === 'rooms' ? 'room' : 'option');

        Object.entries(values).forEach(([name, raw]) => {
            const field = row.querySelector(`[data-field-name="${name}"]`);

            if (field) {
                field.value = Array.isArray(raw) ? raw.join(', ') : (raw ?? '');
            }
        });
    }

    renderProduct(data) {
        this.product = data;
        const bookingType = this.form.querySelector('[name="booking_type"]');
        [...bookingType.options].forEach((option) => { option.hidden = !(option.value in data.booking_types); });
        bookingType.value = Object.keys(data.booking_types)[0] ?? '';

        const units = this.form.querySelector('[data-units]');
        units.hidden = !data.per_unit;
        units.querySelector('[data-unit-list]').innerHTML = data.unit_options.map((unit) => `
            <label class="flex items-center justify-between gap-3 rounded-control border border-ink-100 px-3 py-2 text-sm">
                <span>${escapeHtml(unit.name)} <span class="text-ink-500">(${unit.seats} seats)</span></span>
                <input type="number" min="0" value="0" class="form-control w-20" data-unit="${unit.id}" aria-label="${escapeHtml(unit.name)} quantity">
            </label>`).join('');

        const addons = this.form.querySelector('[data-addons]');
        addons.hidden = data.addons.length === 0;
        addons.querySelector('[data-addon-list]').innerHTML = data.addons.map((addon) => `
            <div class="flex items-center justify-between gap-3 rounded-control border border-ink-100 px-3 py-2 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" data-addon="${addon.id}" ${addon.required ? 'checked disabled' : ''}>
                    <span>${escapeHtml(addon.name)}${addon.required ? ' <span class="text-ink-500">(required)</span>' : ''}</span>
                </label>
                ${addon.pricing_basis === 'per_unit' && !addon.required ? `<input type="number" min="1" max="${addon.max_quantity ?? ''}" placeholder="auto" class="form-control w-20" data-addon-quantity="${addon.id}" aria-label="${escapeHtml(addon.name)} quantity">` : ''}
            </div>`).join('');

        this.optionList = data.room_options;
        const options = this.form.querySelector('[data-room-options]');
        options.hidden = data.room_options.length === 0;
        options.querySelector('[data-rows="room_options"]').innerHTML = '';
    }

    rows(type) {
        return [...this.form.querySelectorAll(`[data-rows="${type}"] [data-row]`)].map((row) => {
            const field = (name) => row.querySelector(`[data-field-name="${name}"]`)?.value ?? '';
            const base = { adults: Number(field('adults') || 0), children_ages: parseAges(field('children_ages')) };

            return type === 'rooms'
                ? { room_id: Number(field('room_id') || 0), ...base, extra_beds: Number(field('extra_beds') || 0), cots: Number(field('cots') || 0) }
                : { room_option_id: Number(field('room_option_id') || 0), ...base };
        });
    }

    units() {
        return [...this.form.querySelectorAll('[data-unit]')]
            .map((input) => ({ unit_option_id: Number(input.dataset.unit), quantity: Number(input.value || 0) }))
            .filter((unit) => unit.quantity > 0);
    }

    /**
     * Selected optional add-ons (required ones are added by the server), with the quantity of per-unit add-ons
     * when staff set one (empty = one per unit).
     */
    addons() {
        return [...this.form.querySelectorAll('[data-addon]:checked:not(:disabled)')].map((input) => {
            const quantity = Number(this.form.querySelector(`[data-addon-quantity="${input.dataset.addon}"]`)?.value || 0);

            return quantity > 0 ? { addon_id: Number(input.dataset.addon), quantity } : { addon_id: Number(input.dataset.addon) };
        });
    }

    editPayload(type) {
        if (type === 'room') {
            return { check_in: value(this.form, 'check_in'), check_out: value(this.form, 'check_out'), rooms: this.rows('rooms'), note: value(this.form, 'note') };
        }

        return {
            travel_date: value(this.form, 'travel_date'),
            adults: Number(value(this.form, 'adults') || 0),
            child_ages: parseAges(value(this.form, 'child_ages')),
            ...(this.form.querySelector('[data-unit-list]') ? { units: this.units() } : {}),
            ...(this.form.querySelector('[data-addon-list]') ? { addons: this.addons() } : {}),
            ...(this.form.querySelector('[data-rows="room_options"]') ? { room_options: this.rows('room_options') } : {}),
            note: value(this.form, 'note'),
        };
    }

    payload() {
        const type = this.productType();

        if (this.mode === 'edit') {
            return this.editPayload(type);
        }

        const payload = {
            product_type: type,
            booker: value(this.form, 'booker'),
            customer_id: value(this.form, 'customer_id'),
            new_customer: { name: value(this.form, 'new_customer[name]'), email: value(this.form, 'new_customer[email]'), phone: value(this.form, 'new_customer[phone]') },
            partner_id: value(this.form, 'partner_id'),
            lead: { name: value(this.form, 'lead[name]'), email: value(this.form, 'lead[email]'), phone: value(this.form, 'lead[phone]') },
            nationality_code: value(this.form, 'nationality_code'),
            currency: value(this.form, 'currency'),
            locale: value(this.form, 'locale'),
            notify: this.form.querySelector('input[type="checkbox"][name="notify"]')?.checked ?? true,
            notes: value(this.form, 'notes'),
        };

        if (type === 'room') {
            return { ...payload, check_in: value(this.form, 'check_in'), check_out: value(this.form, 'check_out'), rooms: this.rows('rooms') };
        }

        return {
            ...payload,
            slug: value(this.form, type === 'package' ? 'package_slug' : 'trip_slug'),
            booking_type: value(this.form, 'booking_type'),
            travel_date: value(this.form, 'travel_date'),
            adults: Number(value(this.form, 'adults') || 0),
            child_ages: parseAges(value(this.form, 'child_ages')),
            units: this.units(),
            addons: this.addons(),
            room_options: this.rows('room_options'),
        };
    }

    invalidate() {
        if (this.mode === 'edit' && this.submitButton) {
            this.previewed = null;
            this.submitButton.disabled = true;
        }
    }

    async quote() {
        const body = this.payload();

        try {
            const response = await request(this.form.dataset.quoteUrl, { method: 'POST', body });
            this.render(response.data);

            if (this.mode === 'edit') {
                this.previewed = JSON.stringify(body);
                this.submitButton.disabled = !response.data.valid;
            }
        } catch (error) {
            this.render(error.data?.data ?? null, error.data?.errors ?? { form: [error.message] });
        }
    }

    async submit() {
        const body = this.payload();

        if (this.mode === 'edit' && this.previewed !== JSON.stringify(body)) {
            toast('Preview the changes first.', 'info');

            return;
        }

        this.submitButton.disabled = true;

        try {
            const response = await request(this.form.dataset.submitUrl, { method: this.form.dataset.submitMethod, body });
            toast(response.message, 'success');
            window.location.assign(response.redirect);
        } catch (error) {
            this.render(null, error.data?.errors ?? { form: [error.message] });
            this.submitButton.disabled = this.mode === 'edit';
        }
    }

    /**
     * Quote (create) or {valid, errors, diff, quote} (edit preview), with optional validation errors.
     */
    render(data, validation = null) {
        const quote = data?.quote ?? (data?.lines ? data : null);
        const errors = { ...(validation ?? {}), ...(data?.errors ?? {}) };
        const messages = [...new Set(Object.values(errors).flat())];
        let html = '';

        if (messages.length) {
            html += `<div role="alert" class="mb-4 space-y-1 rounded-control bg-error/10 px-4 py-3 text-sm text-error">${messages.map((message) => `<p>${escapeHtml(message)}</p>`).join('')}</div>`;
        }

        if (data?.diff) {
            html += `<table class="admin-table mb-4"><thead><tr><th scope="col">Change</th><th scope="col">Before</th><th scope="col">After</th></tr></thead><tbody>${data.diff.map((row) => `
                <tr class="${row.changed ? 'bg-brand-100/40 font-medium' : ''}"><td>${escapeHtml(row.label)}</td><td>${escapeHtml(row.before)}</td><td>${escapeHtml(row.after)}</td></tr>`).join('')}</tbody></table>`;
        }

        if (quote) {
            html += `<table class="admin-table"><tbody>${quote.lines.map((line) => `
                <tr><td>${escapeHtml(line.label)}${line.quantity > 1 ? ` <span class="text-ink-500">× ${line.quantity}</span>` : ''}</td><td class="text-right tabular-nums">${money.format(line.total)}</td></tr>`).join('')}
                <tr><td class="font-semibold">Total</td><td class="text-right text-lg font-bold tabular-nums">${money.format(quote.total)}</td></tr>
                ${quote.partner_total !== undefined && quote.partner_total !== null ? `<tr><td class="text-ink-500">Retail ${money.format(quote.retail_total)} · commission ${quote.commission_percent ?? 0}%</td><td class="text-right tabular-nums">−${money.format(quote.commission_amount)}</td></tr>` : ''}
                <tr><td class="text-ink-500">Deposit (${quote.deposit_percent}%)</td><td class="text-right tabular-nums">${money.format(quote.deposit_amount)}</td></tr>
                <tr><td class="text-ink-500">Balance</td><td class="text-right tabular-nums">${money.format(quote.balance_amount)}</td></tr>
            </tbody></table>`;
        }

        this.result.innerHTML = html || '<p class="text-sm text-ink-500">No price yet.</p>';
    }
}
