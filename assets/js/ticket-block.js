(function(){
  function formatGBP(amount){
    return '£' + Number(amount || 0).toFixed(2);
  }

  function init(container){
    const form = container.querySelector('.ets-ticket-form');
    const button = container.querySelector('.ets-pay-button');
    if (!form || !button) return;

    const subtotalEl = container.querySelector('.ets-subtotal-amount');
    const totalEl = container.querySelector('.ets-total-amount');
    const discountRow = container.querySelector('.ets-discount-row');
    const discountAmountEl = container.querySelector('.ets-discount-amount');
    const discountInput = container.querySelector('.ets-discount-code-input');
    const applyDiscountButton = container.querySelector('.ets-apply-discount-button');
    const discountMessage = container.querySelector('.ets-discount-message');
    const qtyInputs = container.querySelectorAll('.ets-qty-input');
    const addonQtyInputs = container.querySelectorAll('.ets-addon-qty-input');
    const addonCards = container.querySelectorAll('.ets-addon-card');
    const termsCheckbox = container.querySelector('.ets-terms-checkbox');
    const attendeeWrapper = container.querySelector('[data-ets-attendee-wrapper]');
    const attendeeGroups = attendeeWrapper ? attendeeWrapper.querySelector('.ets-attendee-groups') : null;

    let appliedDiscount = null;
    let lastSubtotal = 0;


    const checkoutExperience = container.dataset.etsCheckoutExperience || 'single';
    const isMultiStep = checkoutExperience === 'multi_step';

    // In multi-step mode the real Stripe button is triggered programmatically
    // from the final Review step. Keep it hidden so it does not appear on the Details step.
    if (isMultiStep) {
      button.style.display = 'none';
      button.setAttribute('aria-hidden', 'true');
    }

    const stepEls = Array.from(container.querySelectorAll('.ets-checkout-step'));
    const stepLabels = Array.from(container.querySelectorAll('[data-step-label]'));
    const progressBar = container.querySelector('.ets-checkout-progress-bar span');
    const backButton = container.querySelector('.ets-step-back');
    const nextButton = container.querySelector('.ets-step-next');
    const reviewSummary = container.querySelector('.ets-review-summary');
    let currentStepIndex = 0;

    function getVisibleSteps(){
      if (!isMultiStep) return [];
      return stepEls.filter(step => {
        const id = step.dataset.etsStep || '';
        if (id === 'addons' && !step.querySelector('.ets-addon-card')) return false;
        return true;
      });
    }

    function buildReviewSummary(){
      if (!reviewSummary) return;
      const lines = [];

      qtyInputs.forEach(input => {
        const qty = parseInt(input.value, 10) || 0;
        if (qty <= 0 || input.disabled) return;
        const label = getTicketLabel(input);
        const price = parseFloat(input.dataset.price) || 0;
        lines.push('<li><strong>' + qty + ' × ' + label + '</strong> - ' + formatGBP(qty * price) + '</li>');
      });

      addonQtyInputs.forEach(input => {
        const qty = parseInt(input.value, 10) || 0;
        if (qty <= 0 || input.disabled) return;
        const card = input.closest('.ets-addon-card');
        const title = card ? (card.querySelector('h4') ? card.querySelector('h4').textContent.trim() : 'Add-on') : 'Add-on';
        const price = parseFloat(input.dataset.price) || 0;
        lines.push('<li>' + qty + ' × ' + title + ' - ' + formatGBP(qty * price) + '</li>');
      });

      const subtotal = calculateSubtotal();
      const discountAmount = appliedDiscount ? (appliedDiscount.discount_cents / 100) : 0;
      const finalTotal = Math.max(0, subtotal - discountAmount);

      reviewSummary.innerHTML = '<ul class="ets-review-lines">' + (lines.length ? lines.join('') : '<li>No tickets selected.</li>') + '</ul>' +
        '<div class="ets-review-totals"><p><strong>Subtotal:</strong> ' + formatGBP(subtotal) + '</p>' +
        (discountAmount > 0 ? '<p><strong>Discount:</strong> -' + formatGBP(discountAmount) + '</p>' : '') +
        '<p><strong>Total:</strong> ' + formatGBP(finalTotal) + '</p></div>';
    }

    function validateCurrentStep(stepId){
      if (stepId === 'tickets' && !hasTicketSelected()) {
        alert('Please select at least one ticket before continuing.');
        return false;
      }

      if (stepId === 'details') {
        const nameField = form.querySelector('[name="ets_name"]');
        const emailField = form.querySelector('[name="ets_email"]');
        if (!nameField || !emailField || !nameField.value.trim() || !emailField.value.trim()) {
          alert('Please enter your name and email.');
          if (nameField && !nameField.value.trim()) nameField.focus();
          else if (emailField) emailField.focus();
          return false;
        }
        if (termsCheckbox && !termsCheckbox.checked) {
          alert('Please agree to the terms and conditions.');
          termsCheckbox.focus();
          return false;
        }
      }

      return true;
    }

    function showStep(index){
      if (!isMultiStep) return;
      const steps = getVisibleSteps();
      if (!steps.length) return;
      currentStepIndex = Math.max(0, Math.min(index, steps.length - 1));

      stepEls.forEach(step => step.classList.remove('is-active'));
      steps[currentStepIndex].classList.add('is-active');

      const activeId = steps[currentStepIndex].dataset.etsStep || '';
      stepLabels.forEach(label => {
        const matches = label.dataset.stepLabel === activeId;
        const labelIndex = steps.findIndex(step => (step.dataset.etsStep || '') === label.dataset.stepLabel);
        label.classList.toggle('is-active', matches);
        label.classList.toggle('is-complete', labelIndex > -1 && labelIndex < currentStepIndex);
        label.style.display = labelIndex === -1 ? 'none' : '';
      });

      if (progressBar) {
        progressBar.style.width = steps.length > 1 ? ((currentStepIndex + 1) / steps.length * 100) + '%' : '100%';
      }

      if (backButton) backButton.style.display = currentStepIndex === 0 ? 'none' : '';
      if (nextButton) {
        const isLast = currentStepIndex === steps.length - 1;
        nextButton.textContent = isLast ? 'Pay securely with Stripe' : 'Continue';
        nextButton.disabled = isLast ? button.disabled : false;
      }

      if (activeId === 'review') buildReviewSummary();
      updateButtonState();
    }

    function refreshMultiStep(){
      if (!isMultiStep) return;
      showStep(currentStepIndex);
    }

    function calculateSubtotal(){
      let total = 0;
      qtyInputs.forEach(input => {
        let qty = parseInt(input.value, 10) || 0;
        const max = input.hasAttribute('max') ? parseInt(input.getAttribute('max'), 10) : null;
        if (max !== null && !Number.isNaN(max) && qty > max) {
          qty = max;
          input.value = String(max);
        }
        if (input.disabled) qty = 0;
        const price = parseFloat(input.dataset.price) || 0;
        total += qty * price;
      });
      addonQtyInputs.forEach(input => {
        let qty = parseInt(input.value, 10) || 0;
        const max = input.hasAttribute('max') ? parseInt(input.getAttribute('max'), 10) : null;
        if (max !== null && !Number.isNaN(max) && qty > max) {
          qty = max;
          input.value = String(max);
        }
        if (input.disabled) qty = 0;
        const price = parseFloat(input.dataset.price) || 0;
        total += qty * price;
      });
      return total;
    }

    function hasTicketSelected(){
      return calculateSubtotal() > 0;
    }

    function getTicketLabel(input){
      const name = input.getAttribute('name') || '';
      const match = name.match(/ets_tickets\[(\d+)\]/);
      if (!match) return 'Ticket';

      const labelField = form.querySelector('[name="ets_tickets[' + match[1] + '][label]"]');
      return labelField && labelField.value ? labelField.value : 'Ticket';
    }

    function getTicketIndex(input){
      const name = input.getAttribute('name') || '';
      const match = name.match(/ets_tickets\[(\d+)\]/);
      return match ? match[1] : '';
    }

    function slugify(value){
      return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }

    function getSelectedTicketSlugs(){
      const selected = new Set();
      qtyInputs.forEach(input => {
        const qty = parseInt(input.value, 10) || 0;
        if (qty > 0 && !input.disabled) {
          selected.add(slugify(getTicketLabel(input)));
        }
      });
      return selected;
    }

    function updateAddonVisibility(){
      if (!addonCards.length) return;
      const selected = getSelectedTicketSlugs();
      addonCards.forEach(card => {
        const appliesTo = card.dataset.appliesTo || '';
        const shouldShow = !appliesTo || selected.has(appliesTo);
        card.style.display = shouldShow ? '' : 'none';
        if (!shouldShow) {
          const input = card.querySelector('.ets-addon-qty-input');
          if (input) input.value = '0';
        }
      });
    }

    function renderAttendeeFields(){
      if (!attendeeWrapper || !attendeeGroups) return;

      attendeeGroups.innerHTML = '';
      let hasAttendees = false;

      qtyInputs.forEach(input => {
        if (input.disabled) return;

        const qty = parseInt(input.value, 10) || 0;
        const ticketIndex = getTicketIndex(input);
        const label = getTicketLabel(input);

        if (!ticketIndex || qty <= 0) return;

        hasAttendees = true;

        const group = document.createElement('div');
        group.className = 'ets-attendee-group';

        const heading = document.createElement('h4');
        heading.textContent = label + ' attendees';
        group.appendChild(heading);

        for (let i = 0; i < qty; i++) {
          const row = document.createElement('div');
          row.className = 'ets-attendee-row';

          const title = document.createElement('p');
          title.className = 'ets-attendee-row-title';
          title.textContent = label + ' #' + (i + 1);

          const nameInput = document.createElement('input');
          nameInput.type = 'text';
          nameInput.name = 'ets_attendees[' + ticketIndex + '][' + i + '][name]';
          nameInput.placeholder = 'Attendee name';
          nameInput.className = 'ets-attendee-input ets-attendee-name';

          const emailInput = document.createElement('input');
          emailInput.type = 'email';
          emailInput.name = 'ets_attendees[' + ticketIndex + '][' + i + '][email]';
          emailInput.placeholder = 'Attendee email (optional)';
          emailInput.className = 'ets-attendee-input ets-attendee-email';

          row.appendChild(title);
          row.appendChild(nameInput);
          row.appendChild(emailInput);
          group.appendChild(row);
        }

        attendeeGroups.appendChild(group);
      });

      attendeeWrapper.style.display = hasAttendees ? '' : 'none';
    }

    function updateButtonState(){
      const termsOk = termsCheckbox ? termsCheckbox.checked : true;
      button.disabled = !hasTicketSelected() || !termsOk;
      if (isMultiStep && nextButton) {
        const steps = getVisibleSteps();
        const active = steps[currentStepIndex];
        if (active && (active.dataset.etsStep || '') === 'review') {
          nextButton.disabled = button.disabled;
        }
      }
    }

    function clearDiscount(message){
      appliedDiscount = null;
      if (discountRow) discountRow.style.display = 'none';
      if (discountAmountEl) discountAmountEl.textContent = '-£0.00';
      if (discountMessage) {
        discountMessage.textContent = message || '';
        discountMessage.classList.remove('text-green-400');
        if (message) discountMessage.classList.add('text-red-400');
      }
    }

    function updateTotal(){
      const subtotal = calculateSubtotal();

      if (appliedDiscount && Math.round(subtotal * 100) !== appliedDiscount.subtotal_cents) {
        clearDiscount('Ticket selection changed. Please re-apply your discount code.');
      }

      const discountAmount = appliedDiscount ? (appliedDiscount.discount_cents / 100) : 0;
      const finalTotal = Math.max(0, subtotal - discountAmount);

      if (subtotalEl) subtotalEl.textContent = formatGBP(subtotal);
      if (totalEl) totalEl.textContent = formatGBP(finalTotal);
      if (discountRow) discountRow.style.display = appliedDiscount ? '' : 'none';
      if (discountAmountEl) discountAmountEl.textContent = '-' + formatGBP(discountAmount);

      updateButtonState();
    }

    qtyInputs.forEach(input => input.addEventListener('input', function(){
      renderAttendeeFields();
      updateAddonVisibility();
      updateTotal();
      refreshMultiStep();
    }));
    addonQtyInputs.forEach(input => input.addEventListener('input', function(){
      updateTotal();
      refreshMultiStep();
    }));
    if (termsCheckbox) termsCheckbox.addEventListener('change', function(){
      updateButtonState();
      refreshMultiStep();
    });

    if (backButton) {
      backButton.addEventListener('click', function(){
        showStep(currentStepIndex - 1);
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', function(e){
        e.preventDefault();
        const steps = getVisibleSteps();
        const active = steps[currentStepIndex];
        const activeId = active ? (active.dataset.etsStep || '') : '';

        if (!validateCurrentStep(activeId)) return;

        if (currentStepIndex >= steps.length - 1) {
          button.click();
          return;
        }

        showStep(currentStepIndex + 1);
      });
    }

    const restUrl = container.dataset.etsRestUrl;
    const stripeKey = container.dataset.etsStripePk;
    const discountRestUrl = restUrl ? restUrl.replace('/create-checkout-session', '/validate-discount') : '';
    const waitingListUrl = container.dataset.etsWaitingListUrl || (restUrl ? restUrl.replace('/create-checkout-session', '/join-waiting-list') : '');

    container.querySelectorAll('.ets-waiting-toggle').forEach(toggle => {
      toggle.addEventListener('click', function(){
        const box = toggle.closest('.ets-waiting-list-box');
        const waitingForm = box ? box.querySelector('.ets-waiting-list-form') : null;
        if (!waitingForm) return;
        waitingForm.style.display = waitingForm.style.display === 'none' || waitingForm.style.display === '' ? 'block' : 'none';
      });
    });

    container.querySelectorAll('.ets-waiting-list-form').forEach(waitingForm => {
      const submit = waitingForm.querySelector('.ets-waiting-submit');
      if (!submit) return;

      submit.addEventListener('click', async function(e){
        e.preventDefault();
        e.stopPropagation();

        const message = waitingForm.querySelector('.ets-waiting-message');
        const requiredFields = waitingForm.querySelectorAll('[required]');

        for (const field of requiredFields) {
          if (!field.value.trim()) {
            if (message) {
              message.textContent = 'Please fill in the waiting list details.';
              message.classList.remove('text-green-400');
              message.classList.add('text-red-400');
            }
            field.focus();
            return;
          }
        }

        if (!waitingListUrl) {
          if (message) message.textContent = 'Waiting list is not available right now.';
          return;
        }

        const originalText = submit.textContent;
        submit.disabled = true;
        submit.textContent = 'Joining...';

        if (message) {
          message.textContent = '';
          message.classList.remove('text-red-400', 'text-green-400');
        }

        try {
          const formData = new FormData();
          waitingForm.querySelectorAll('input, select, textarea').forEach(input => {
            if (!input.name) return;
            formData.append(input.name, input.value);
          });

          const response = await fetch(waitingListUrl, { method: 'POST', body: formData });
          const data = await response.json();

          if (!response.ok || data.error) {
            if (message) {
              message.textContent = data.error || 'Could not join the waiting list. Please try again.';
              message.classList.add('text-red-400');
            }
            return;
          }

          waitingForm.querySelectorAll('input:not([type=hidden]), textarea').forEach(input => {
            if (input.type === 'number') {
              input.value = input.min || '1';
            } else {
              input.value = '';
            }
          });

          if (message) {
            message.textContent = data.message || 'You have joined the waiting list.';
            message.classList.add('text-green-400');
          }
        } catch (error) {
          console.error(error);
          if (message) {
            message.textContent = 'Could not join the waiting list. Please try again.';
            message.classList.add('text-red-400');
          }
        } finally {
          submit.disabled = false;
          submit.textContent = originalText;
        }
      });
    });

    if (applyDiscountButton && discountInput && discountRestUrl) {
      applyDiscountButton.addEventListener('click', async function(e){
        e.preventDefault();

        const code = discountInput.value.trim();
        if (!code) {
          clearDiscount('Please enter a discount code.');
          updateTotal();
          return;
        }

        if (!hasTicketSelected()) {
          clearDiscount('Please select at least one ticket before applying a discount.');
          updateTotal();
          return;
        }

        const originalText = applyDiscountButton.textContent;
        applyDiscountButton.disabled = true;
        applyDiscountButton.textContent = 'Checking...';

        try {
          const formData = new FormData(form);
          const response = await fetch(discountRestUrl, { method: 'POST', body: formData });
          const data = await response.json();

          if (!response.ok || data.error) {
            clearDiscount(data.error || 'Discount code is not valid.');
            updateTotal();
            return;
          }

          appliedDiscount = data;
          discountInput.value = data.code || code.toUpperCase();
          if (discountMessage) {
            discountMessage.textContent = data.message || 'Discount applied.';
            discountMessage.classList.remove('text-red-400');
            discountMessage.classList.add('text-green-400');
          }
          updateTotal();
        } catch (error) {
          console.error(error);
          clearDiscount('Could not validate discount code. Please try again.');
          updateTotal();
        } finally {
          applyDiscountButton.disabled = false;
          applyDiscountButton.textContent = originalText;
        }
      });
    }

    updateAddonVisibility();
    updateTotal();
    showStep(0);

    if (!restUrl || !stripeKey || typeof Stripe === 'undefined') return;

    const stripe = Stripe(stripeKey);

    button.addEventListener('click', async function(e){
      e.preventDefault();

      if (button.disabled) return;

      const nameField = form.querySelector('[name="ets_name"]');
      const emailField = form.querySelector('[name="ets_email"]');

      if (!nameField || !emailField || !nameField.value.trim() || !emailField.value.trim()) {
        alert('Please enter your name and email.');
        return;
      }

      if (termsCheckbox && !termsCheckbox.checked) {
        alert('Please agree to the terms and conditions.');
        return;
      }

      const formData = new FormData(form);
      const originalText = button.textContent;
      button.disabled = true;
      button.textContent = 'Redirecting to Stripe...';

      try {
        const response = await fetch(restUrl, { method: 'POST', body: formData });
        const data = await response.json();

        if (!response.ok || data.error) {
          alert(data.error || 'Unable to start checkout. Please try again.');
          button.textContent = originalText;
          updateButtonState();
          return;
        }

        const result = await stripe.redirectToCheckout({ sessionId: data.id });
        if (result.error) {
          alert(result.error.message);
          button.textContent = originalText;
          updateButtonState();
        }
      } catch (error) {
        console.error(error);
        alert('An unexpected error occurred.');
        button.textContent = originalText;
        updateButtonState();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.ets-ticket-block').forEach(init);
  });
})();
