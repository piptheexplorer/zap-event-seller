(function () {
    function extractTicketId(value) {
        if (!value) return '';
        value = String(value).trim();

        try {
            const parsed = JSON.parse(value);
            if (parsed && parsed.ticket_id) return String(parsed.ticket_id).trim();
        } catch (e) {}

        try {
            const url = new URL(value);
            return url.searchParams.get('ticket_id') || url.searchParams.get('ticket_pdf') || value;
        } catch (e) {}

        return value;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderAddonRows(ticket) {
        if (!ticket.addons || !Array.isArray(ticket.addons) || !ticket.addons.length) {
            return '';
        }

        const items = ticket.addons.map(addon => {
            const name = escapeHtml(addon.name || '');
            const price = addon.price ? ' <span>(' + escapeHtml(addon.price) + ')</span>' : '';
            return '<li>' + name + price + '</li>';
        }).join('');

        return '<div class="ets-checkin-addons"><p><strong>Add-ons:</strong></p><ul>' + items + '</ul></div>';
    }

    function renderResult(container, data, type) {
        const result = container.querySelector('.ets-checkin-result');
        if (!result) return;

        const ticket = data.ticket || data;
        const checked = !!ticket.checked_in;
        const statusClass = type === 'error' ? 'ets-result-error' : checked ? 'ets-result-warning' : 'ets-result-success';
        const checkedText = checked
            ? `<p><strong>Status:</strong> Already checked in${ticket.checked_in_at ? ' at ' + ticket.checked_in_at : ''}${ticket.checked_in_by ? ' by ' + ticket.checked_in_by : ''}</p>`
            : '<p><strong>Status:</strong> Valid, not yet checked in</p>';

        result.className = 'ets-checkin-result ' + statusClass;
        const addonRows = renderAddonRows(ticket);
        const ticketKind = ticket.ticket_kind === 'event_addon' ? '<p><strong>Type:</strong> Event-wide add-on pass</p>' : '';

        result.innerHTML = `
            <h3>${escapeHtml(data.message || (checked ? 'Ticket already used' : 'Valid ticket'))}</h3>
            <p><strong>Ticket ID:</strong> ${escapeHtml(ticket.ticket_id || '')}</p>
            <p><strong>Ticket:</strong> ${escapeHtml(ticket.ticket_type || '')} ${ticket.price ? '(' + escapeHtml(ticket.price) + ')' : ''}</p>
            ${ticketKind}
            ${addonRows}
            ${ticket.attendee_name ? '<p><strong>Attendee:</strong> ' + escapeHtml(ticket.attendee_name) + (ticket.attendee_email ? ' &lt;' + escapeHtml(ticket.attendee_email) + '&gt;' : '') + '</p>' : ''}
            <p><strong>Customer:</strong> ${escapeHtml(ticket.customer_name || '')}</p>
            <p><strong>Event:</strong> ${escapeHtml(ticket.event_title || '')}</p>
            <p><strong>Date:</strong> ${escapeHtml(ticket.event_date || '')} ${escapeHtml(ticket.event_time || '')}</p>
            <p><strong>Location:</strong> ${escapeHtml(ticket.event_location || '')}</p>
            ${checkedText}
        `;
    }

    async function postJSON(url, nonce, ticketId) {
        const form = new FormData();
        form.append('ticket_id', ticketId);

        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            body: form
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(data.message || data.error || 'Request failed');
            error.data = data;
            throw error;
        }
        return data;
    }

    function initCheckin(container) {
        const validateUrl = container.dataset.validateUrl;
        const checkinUrl = container.dataset.checkinUrl;
        const nonce = container.dataset.nonce;
        const input = container.querySelector('#ets-ticket-id-input');
        const validateBtn = container.querySelector('.ets-validate-ticket');
        const checkinBtn = container.querySelector('.ets-checkin-ticket');
        const startBtn = container.querySelector('.ets-start-scanner');
        const stopBtn = container.querySelector('.ets-stop-scanner');
        let lastTicketId = '';
        let scanner = null;
        let scannerRunning = false;

        async function validate(ticketValue) {
            const ticketId = extractTicketId(ticketValue || input.value);
            if (!ticketId) {
                alert('Please enter or scan a ticket ID.');
                return;
            }
            input.value = ticketId;
            lastTicketId = ticketId;
            checkinBtn.disabled = true;

            try {
                const data = await postJSON(validateUrl, nonce, ticketId);
                renderResult(container, data, 'success');
                checkinBtn.disabled = !!(data.checked_in || (data.ticket && data.ticket.checked_in));
            } catch (error) {
                renderResult(container, error.data || { message: error.message }, 'error');
            }
        }

        validateBtn && validateBtn.addEventListener('click', function () {
            validate(input.value);
        });

        input && input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                validate(input.value);
            }
        });

        checkinBtn && checkinBtn.addEventListener('click', async function () {
            const ticketId = lastTicketId || extractTicketId(input.value);
            if (!ticketId) return;

            checkinBtn.disabled = true;
            try {
                const data = await postJSON(checkinUrl, nonce, ticketId);
                renderResult(container, data, 'success');
            } catch (error) {
                renderResult(container, error.data || { message: error.message }, 'error');
            }
        });

        startBtn && startBtn.addEventListener('click', async function () {
            if (typeof Html5Qrcode === 'undefined') {
                alert('QR scanner library could not be loaded. You can still enter the ticket ID manually.');
                return;
            }

            if (!scanner) scanner = new Html5Qrcode('ets-qr-reader');
            if (scannerRunning) return;

            try {
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    function (decodedText) {
                        validate(decodedText);
                    }
                );
                scannerRunning = true;
                startBtn.disabled = true;
                stopBtn.disabled = false;
            } catch (error) {
                alert('Could not start camera scanner. Please allow camera access or use manual entry.');
            }
        });

        stopBtn && stopBtn.addEventListener('click', async function () {
            if (!scanner || !scannerRunning) return;
            await scanner.stop();
            scannerRunning = false;
            startBtn.disabled = false;
            stopBtn.disabled = true;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ets-checkin').forEach(initCheckin);
    });
})();
